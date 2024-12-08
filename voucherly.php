<?php

/**
 * Copyright (C) 2024 Voucherly
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @author    Voucherly <info@voucherly.it>
 * @copyright 2024 Voucherly
 * @license   https://opensource.org/license/gpl-3-0/ GNU General Public License version 3 (GPL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Voucherly extends PaymentModule
{
    /**
     * Voucherly Prestashop configuration
     * use Configuration::get(Voucherly::CONST_NAME) to return a value
     */
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'voucherly';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'Voucherly';
        $this->need_instance = 1;
        $this->module_key = '812ed8ea2509dd2146ef979a6af24ee5';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Voucherly');
        $this->description = $this->l('Accept meal vouchers directly on your e-commerce. Secure every sale with safe and flexible online payments.');
        $this->limited_currencies = ['EUR'];
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        $this->loadConfiguration();
    }

    protected function loadConfiguration()
    {
        $this->loadVoucherlyApiKey();

        VoucherlyApi\Api::setOsVersionHeader('PrestaShop');
        VoucherlyApi\Api::setOsVersionHeader(_PS_VERSION_);
        VoucherlyApi\Api::setAppNameHeader('voucherly-prestashop');
        VoucherlyApi\Api::setAppVersionHeader($this->version);
        VoucherlyApi\Api::setDeviceTypeHeader('ECOMMERCE-PLUGIN');
    }

    private function loadVoucherlyApiKey()
    {
        if (Configuration::get('VOUCHERLY_SANDBOX', true)) {
            VoucherlyApi\Api::setApiKey(Configuration::get('VOUCHERLY_SAND_KEY', ''));
        } else {
            VoucherlyApi\Api::setApiKey(Configuration::get('VOUCHERLY_LIVE_KEY', ''));
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        Configuration::updateValue('VOUCHERLY_SANDBOX', true);
        Configuration::updateValue('VOUCHERLY_LIVE_KEY', '');
        Configuration::updateValue('VOUCHERLY_SAND_KEY', '');
        Configuration::updateValue('VOUCHERLY_SHIPPING_FOOD', true);
        Configuration::updateValue('VOUCHERLY_FOOD_CATEGORY', '');

        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
        && $this->registerHook('paymentOptions')
        && $this->registerHook('displayAdminOrderMainBottom');
    }

    public function uninstall()
    {
        Configuration::deleteByName('VOUCHERLY_SANDBOX');
        Configuration::deleteByName('VOUCHERLY_LIVE_KEY');
        Configuration::deleteByName('VOUCHERLY_SAND_KEY');
        Configuration::deleteByName('VOUCHERLY_SHIPPING_FOOD');
        Configuration::deleteByName('VOUCHERLY_FOOD_CATEGORY');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    
    private function renderForm()
    {
        $this->loadConfiguration();

        if (((bool) Tools::isSubmit('submitVoucherlyModuleRefund')) == true && !empty(Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID'))) {
            $refund = $this->refundVoucherlyPayment(Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID'));
            if (!is_numeric($refund)) {
                return $this->renderRefundForm($refund);
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => (int)$refund,
                'vieworder' => ''
            ]));
            return '';
        }
        
        if (((bool) Tools::isSubmit('submitVoucherlyModuleConfig')) == true) {
            return $this->renderConfigForm($this->postProcessConfig());
        }

        $form = Tools::getValue('form');
        if ($form === 'refund') {
            return $this->renderRefundForm();
        }

        return $this->renderConfigForm();
    }
    

    private function renderConfigForm($postProcessResult = null) {
        
        $configForm = new HelperForm();

        $configForm->show_toolbar = false;
        $configForm->table = $this->table;
        $configForm->module = $this;
        $configForm->default_form_language = $this->context->language->id;
        $configForm->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $configForm->identifier = $this->identifier;
        $configForm->submit_action = 'submitVoucherlyModuleConfig';
        $configForm->currentIndex = $this->getConfigFormLink([
            'tab_module' => $this->tab,
            'module_name' => $this->name
        ]);
        $configForm->token = Tools::getAdminTokenLite('AdminModules');

        $configForm->tpl_vars = [
            'fields_value' => [
                'VOUCHERLY_SANDBOX' => Configuration::get('VOUCHERLY_SANDBOX', false),
                'VOUCHERLY_LIVE_KEY' => Configuration::get('VOUCHERLY_LIVE_KEY', ''),
                'VOUCHERLY_SAND_KEY' => Configuration::get('VOUCHERLY_SAND_KEY', ''),
                'VOUCHERLY_SHIPPING_FOOD' => Configuration::get('VOUCHERLY_SHIPPING_FOOD', false),
                'VOUCHERLY_FOOD_CATEGORY' => Configuration::get('VOUCHERLY_FOOD_CATEGORY', ''),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $success = '';
        $error = '';

        $ok = VoucherlyApi\Api::testAuthentication();
        if (!$ok) {
            $error = sprintf($this->l('Voucherly is not correctly configured, get an API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>') . '<br />';
        }


        if (!empty($postProcessResult)) {
            $success .= $postProcessResult['success'];
            $error .= $postProcessResult['error'];
        }

        $categorys = Category::getSimpleCategories($this->context->language->id);

        foreach ($categorys as $attribute) {
            $selectAttributes[] = [
                'id_category' => $attribute['id_category'],
                'name' => $attribute['name'],
            ];
        }

        return $configForm->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'success' => $success,
                'error' => $error,
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key live',
                        'name' => 'VOUCHERLY_LIVE_KEY',
                        'desc' => sprintf($this->l('Locate API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key sandbox',
                        'name' => 'VOUCHERLY_SAND_KEY',
                        'desc' => sprintf($this->l('Locate API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Sandbox Mode'),
                        'name' => 'VOUCHERLY_SANDBOX',
                        'is_bool' => true,
                        'desc' => $this->l('Sandbox Mode can be used to test payments.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Category for food products'),
                        'desc' => $this->l('Select the category that determines whether a product qualifies as food (eligible for meal voucher payment). If no category is selected, all products will be considered food.'),
                        'name' => 'VOUCHERLY_FOOD_CATEGORY',
                        'required' => false,
                        'options' => [
                            'query' => array_merge(
                                [
                                    [
                                        'id_category' => '',
                                        'name' => '',
                                    ],
                                ],
                                $selectAttributes
                            ),
                            'id' => 'id_category',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Shipping as food'),
                        'name' => 'VOUCHERLY_SHIPPING_FOOD',
                        'is_bool' => true,
                        'desc' => $this->l('If shipping is considered food, the customer can pay for it with meal vouchers.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ]]);
    }
    

    private function renderRefundForm($error = '') {

        $refundForm = new HelperForm();

        $refundForm->show_toolbar = false;
        $refundForm->table = $this->table;
        $refundForm->module = $this;
        $refundForm->show_cancel_button = true;
        $refundForm->default_form_language = $this->context->language->id;
        $refundForm->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $refundForm->identifier = $this->identifier;
        $refundForm->submit_action = 'submitVoucherlyModuleRefund';
        $refundForm->currentIndex = $this->getRefundFormLink(Tools::getValue("p"), [
            'tab_module' => $this->tab,
            'module_name' => $this->name
        ]);
        $refundForm->token = Tools::getAdminTokenLite('AdminModules');

        $refundForm->tpl_vars = [
            'fields_value' => [
                'VOUCHERLY_REFUND_PAYMENT_ID' => Tools::getValue("p")
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $refundForm->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Refund'),
                    'icon' => 'icon-credit-card'
                ],
                'error' => $error,
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Payment ID'),
                        'name' => 'VOUCHERLY_REFUND_PAYMENT_ID',
                        'desc' => $this->l('Get the Payment ID from Order details > Payment > Transaction ID.'),
                        'required' => true,
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Refund'),
                ],
            ],
        ]]);
    }

    protected function postProcessConfig()
    {
        $liveOk = $this->processApiKey('live');
        if (!$liveOk) {
            return [
                'success' => '',
                'error' => sprintf($this->l('The "%s" is invalid.'), 'API key live'),
            ];
        }

        $sandOk = $this->processApiKey('sand');
        if (!$sandOk) {
            return [
                'success' => '',
                'error' => sprintf($this->l('The "%s" is invalid.'), 'API key sandbox'),
            ];
        }

        Configuration::updateValue('VOUCHERLY_SANDBOX', Tools::getValue('VOUCHERLY_SANDBOX') == '1');

        $this->loadVoucherlyApiKey();

        Configuration::updateValue('VOUCHERLY_SHIPPING_FOOD', Tools::getValue('VOUCHERLY_SHIPPING_FOOD') == '1');
        Configuration::updateValue('VOUCHERLY_FOOD_CATEGORY', Tools::getValue('VOUCHERLY_FOOD_CATEGORY'));

        $this->getAndUpdatePaymentGateways();

        return [
            'success' => $this->l('Successfully saved.'),
            'error' => '',
        ];
    }

    private function refundVoucherlyPayment($paymentId)
    {
        try {

            $payment = VoucherlyApi\Payment\Payment::get($paymentId);
            $orderId = Order::getIdByCartId((int) $payment->metadata->cartId);
            $order = new Order($orderId);
            if (false === Validate::isLoadedObject($order)) {
                return sprintf($this->l('Payment "%s" has no order.'), $paymentId);
            }

            if ('Refunded' !== $payment->status && 'Cancelled' !== $payment->status) {
                $payment = VoucherlyApi\Payment\Payment::refund($paymentId);
            }
                
            if ('Refunded' !== $payment->status && 'Cancelled' !== $payment->status) {
                throw new Exception('Voucherly refund error');
                // return sprintf($this->l('Unable to refund Payment "%s".'), $paymentId);
            }
                
            if ($order->current_state != Configuration::get('PS_OS_REFUND')) {

                $orderHistory = new OrderHistory();
                $orderHistory->id_order = $orderId;
                $orderHistory->changeIdOrderState(Configuration::get('PS_OS_REFUND'), $order, true);
                $orderHistory->add();
            }

            return $orderId;

        } catch (Exception $ex) {
            return sprintf($this->l('Unable to refund Payment "%s".'), $paymentId);
        }
    }

    private function processApiKey($environment): bool
    {
        $optionKey = 'VOUCHERLY_' . strtoupper($environment) . '_KEY';

        $apiKey = Configuration::get($optionKey, '');
        $newApiKey = Tools::getValue($optionKey);

        if (!empty($newApiKey)) {
            $ok = VoucherlyApi\Api::testAuthentication($newApiKey);
            if (!$ok) {
                return false;
            }
        }

        Configuration::updateValue($optionKey, $newApiKey);

        // Should I delete user metadata?

        return true;
    }

    private function getAndUpdatePaymentGateways()
    {
        $gateways = $this->getPaymentGateways();
        Configuration::updateValue('VOUCHERLY_GATEWAYS', json_encode($gateways));
    }

    private function getPaymentGateways()
    {
        $paymentGatewaysResponse = VoucherlyApi\PaymentGateway\PaymentGateway::list();
        $paymentGateways = $paymentGatewaysResponse->items;
        $gateways = [];

        foreach ($paymentGateways as $gateway) {
            if ($gateway->isActive && !$gateway->merchantConfiguration->isFallback) {
                $formattedGateway['id'] = $gateway->id;
                $formattedGateway['src'] = $gateway->icon ?? $gateway->checkoutImage;
                $formattedGateway['alt'] = $gateway->name;

                $gateways[] = $formattedGateway;
            }
        }

        return $gateways;
    }

    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int) $currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/front/payment_label.tpl');
    }

    public function hookPaymentOptions($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int) $currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $gateways = Configuration::get('VOUCHERLY_GATEWAYS', []);
        $this->smarty->assign([
            'gateways' => json_decode($gateways),
        ]);

        $options = [];

        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption
            ->setCallToActionText($this->l('Pay with Voucherly (meal vouchers, cards and alternative methods)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_logo.png'))
            ->setAdditionalInformation($this->fetch('module:voucherly/views/templates/front/payment_additional.tpl'));

        $options[] = $paymentOption;

        $voucherlyCustomerId = VoucherlyUsers::getVoucherlyId($this->context->customer->id);
        if (!isset($voucherlyCustomerId) || empty($voucherlyCustomerId)) {
            return $options;
        }

        $customerPaymentMethods = VoucherlyApi\Customer\Customer::paymentMethods($voucherlyCustomerId)->items;
        if (empty($customerPaymentMethods)) {
            return $options;
        }

        foreach ($customerPaymentMethods as $customerPaymentMethod) {
            if (!isset($customerPaymentMethod->creditCard)) {
                continue;
            }

            $params = [
                'pm' => $customerPaymentMethod->id,
            ];

            $card = $customerPaymentMethod->creditCard;

            if ($card->expirationMonth < date('m') && $card->expirationYear <= date('Y')) {
                continue;
            }

            $brandImagePath = _PS_MODULE_DIR_ . $this->name . '/views/img/cards/' . $card->brand . '.png';
            if (!file_exists($brandImagePath)) {
                $brandImagePath = _PS_MODULE_DIR_ . $this->name . '/views/img/cards/default.png';
            }

            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option
                ->setModuleName($this->name)
                ->setCallToActionText(Tools::ucfirst($card->brand) . ' ' . $card->pan)
                ->setAction($this->context->link->getModuleLink($this->name, 'payment', $params, true))
                ->setLogo(Media::getMediaPath($brandImagePath));

            $options[] = $option;
        }

        return $options;
    }
    
    public function hookDisplayAdminOrderMainBottom(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);
        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }
        
        $orderPayments = $order->getOrderPayments();
        if (empty($orderPayments)){
            return '';
        }

        $voucherlyId = $orderPayments[0]->transaction_id;

        $voucherlyDashboardLink = 'https://dashboard.voucherly.it/pay/payment/details?id=' . $voucherlyId;
        $refundFormLink = $this->getRefundFormLink($voucherlyId, [
            'token' => Tools::getAdminTokenLite('AdminModules')
        ]);

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoImageSrc' => $this->getPathUri() . 'logo.png',
            'voucherlyDashboardLink' =>  $voucherlyDashboardLink,
            'refundFormLink' =>  $refundFormLink,
        ]);

        return $this->context->smarty->fetch('module:voucherly/views/templates/admin/displayAdminOrderMainBottom.tpl');
    }

    
    private function getConfigFormLink() {
        return $this->context->link->getAdminLink('AdminModules', false, [], [
            'configure' => $this->name
        ]);
    }
    
    private function getRefundFormLink($paymentId, array $additionalQueryParameters = []) {
        return $this->context->link->getAdminLink('AdminModules', false, [], array_merge($additionalQueryParameters, [
            'configure' => $this->name,
            'p' => $paymentId,
            'form' => 'refund'
        ]));
    }
}
