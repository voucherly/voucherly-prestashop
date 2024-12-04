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

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once dirname(__FILE__) . '/voucherly-sdk/init.php';

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

        VoucherlyApi\Api::setPluginNameHeader('PrestaShop');
        VoucherlyApi\Api::setPluginVersionHeader($this->version);
        VoucherlyApi\Api::setPlatformVersionHeader(_PS_VERSION_);
        VoucherlyApi\Api::setTypeHeader('ECOMMERCE-PLUGIN');
    }    

    private function loadVoucherlyApiKey()
    {
        if (Configuration::get('VOUCHERLY_SANDBOX', true))
        {
            VoucherlyApi\Api::setApiKey(Configuration::get('VOUCHERLY_SAND_KEY', ''));
        }
        else
        {
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
            && $this->registerHook('paymentOptions');
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
        $postProcessConfigResult = null;
        if (((bool) Tools::isSubmit('submitVoucherlyModuleConfig')) == true) {
            $postProcessConfigResult = $this->postProcessConfig();
        }

        $postProcessRefundResult = null;
        if (((bool) Tools::isSubmit('submitVoucherlyModuleRefund')) == true) {
            $postProcessRefundResult = $this->postProcessRefund();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm($postProcessConfigResult, $postProcessRefundResult);
    }

    protected function renderForm($postProcessConfigResult, $postProcessRefundResult)
    {
        $this->loadConfiguration();

        $configForm = new HelperForm();

        $configForm->show_toolbar = false;
        $configForm->table = $this->table;
        $configForm->module = $this;
        $configForm->default_form_language = $this->context->language->id;
        $configForm->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $configForm->identifier = $this->identifier;
        $configForm->submit_action = 'submitVoucherlyModuleConfig';
        $configForm->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $configForm->token = Tools::getAdminTokenLite('AdminModules');

        $configForm->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $refundForm = new HelperForm();

        $refundForm->show_toolbar = false;
        $refundForm->table = $this->table;
        $refundForm->module = $this;
        $refundForm->default_form_language = $this->context->language->id;
        $refundForm->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $refundForm->identifier = $this->identifier;
        $refundForm->submit_action = 'submitVoucherlyModuleRefund';
        $refundForm->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $refundForm->token = Tools::getAdminTokenLite('AdminModules');

        $refundForm->tpl_vars = [
            'fields_value' => $this->getRefundFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $configFormSuccess = '';
        $configFormError = '';
        if (!empty($postProcessConfigResult)) {
            $error = $postProcessConfigResult['error'];
            $success = $postProcessConfigResult['success'];

            if (!empty($error)) {
                $configFormError .= $error . '<br />';
            }

            if (!empty($success)) {
                $configFormSuccess = $success;
            }
        }

        $ok = VoucherlyApi\Api::testAuthentication();
        if (!$ok) {
            $configFormError .= sprintf($this->l('Voucherly is not correctly configured, get an API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>') . '<br />';
        }

        $refundFormSuccess = '';
        $refundFormError = '';
        if (!empty($postProcessRefundResult)) {
            $error = $postProcessRefundResult['error'];
            $success = $postProcessRefundResult['success'];

            if (!empty($error)) {
                $refundFormError = $error;
            }

            if (!empty($success)) {
                $refundFormSuccess = $success;
            }
        }

        return $configForm->generateForm([$this->getConfigForm($configFormSuccess, $configFormError)]) .
            $refundForm->generateForm([$this->getRefundForm($refundFormSuccess, $refundFormError)]);
    }

    protected function getConfigForm($configFormSuccess, $configFormError)
    {
        $categorys = Category::getSimpleCategories($this->context->language->id);

        foreach ($categorys as $attribute) {
            $selectAttributes[] = [
                'id_category' => $attribute['id_category'],
                'name' => $attribute['name'],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'success' => $configFormSuccess,
                'error' => $configFormError,
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
        ];
    }

    protected function getConfigFormValues()
    {
        return [
            'VOUCHERLY_SANDBOX' => Configuration::get('VOUCHERLY_SANDBOX', false),
            'VOUCHERLY_LIVE_KEY' => Configuration::get('VOUCHERLY_LIVE_KEY', ''),
            'VOUCHERLY_SAND_KEY' => Configuration::get('VOUCHERLY_SAND_KEY', ''),
            'VOUCHERLY_SHIPPING_FOOD' => Configuration::get('VOUCHERLY_SHIPPING_FOOD', false),
            'VOUCHERLY_FOOD_CATEGORY' => Configuration::get('VOUCHERLY_FOOD_CATEGORY', ''),
        ];
    }

    protected function getRefundForm($refundFormSuccess, $refundFormError)
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Refund'),
                ],
                'success' => $refundFormSuccess,
                'error' => $refundFormError,
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Payment ID'),
                        'name' => 'VOUCHERLY_REFUND_PAYMENT_ID',
                        'desc' => $this->l('Get the Payment ID from Order details > Payment > Transaction ID.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Amount'),
                        'name' => 'VOUCHERLY_REFUND_AMOUNT',
                        'desc' => $this->l('Leave empty to refund the total amount.') . '<br />' . $this->l('Decimals must be divided with a dot.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Refund'),
                ],
            ],
        ];
    }

    protected function getRefundFormValues()
    {
        return [
            'VOUCHERLY_REFUND_PAYMENT_ID' => Configuration::get('VOUCHERLY_REFUND_PAYMENT_ID', ''),
            'VOUCHERLY_REFUND_AMOUNT' => Configuration::get('VOUCHERLY_REFUND_AMOUNT', ''),
        ];
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

    protected function postProcessRefund()
    {
        $postedRefundPaymentId = Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID');
        $postedRefundAmount = Tools::getValue('VOUCHERLY_REFUND_AMOUNT');

        if (empty($postedRefundPaymentId)) {
            return [];
        }

        try {
            $refund = [
                'flow' => 'REFUND',
                'currency' => 'EUR',
                'parent_payment_uid' => $postedRefundPaymentId,
            ];

            if ($postedRefundAmount != '') {
                $refund['amount_unit'] = $postedRefundAmount * 100;
            }

            VoucherlyGBusiness\Payment::create($refund);
        } catch (Exception $ex) {
            return [
                'success' => '',
                'error' => sprintf($this->l('Unable to refund Payment "%s".'), $postedRefundPaymentId),
            ];
        }

        return [
            'success' => sprintf($this->l('Successfully refunded Payment "%s".'), $postedRefundPaymentId),
            'error' => '',
        ];
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

        $this->smarty->assign(
            $this->getPaymentAdditionalTemplateVars()
        );

        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption
            ->setCallToActionText($this->l('Pay with Voucherly (meal vouchers, cards and alternative methods)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_logo.png'))
            ->setAdditionalInformation($this->fetch('module:voucherly/views/templates/front/payment_additional.tpl'));

        return [$paymentOption];
    }

    private function getPaymentAdditionalTemplateVars()
    {
        $gateways = Configuration::get('VOUCHERLY_GATEWAYS', []);

        return [
            'gateways' => json_decode($gateways),
        ];
    }
}
