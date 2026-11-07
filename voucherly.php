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
    private const DASHBOARD_URL = 'https://dashboard.voucherly.it';

    protected $limited_currencies = ['EUR'];

    /** @var VoucherlyApi\VoucherlyClient|null */
    private $voucherlyClient;

    public function __construct()
    {
        $this->name = 'voucherly';
        $this->tab = 'payments_gateways';
        $this->version = '2.2.0';
        $this->author = 'Voucherly';
        $this->need_instance = 1;
        $this->module_key = '812ed8ea2509dd2146ef979a6af24ee5';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Voucherly');
        $this->description = $this->l('Accept meal vouchers directly on your e-commerce. Secure every sale with safe and flexible online payments.');
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.99.99'];
    }

    /**
     * The client is created on first use because it rejects an empty key, which is the state of a fresh install.
     */
    public function getVoucherlyClient(): VoucherlyApi\VoucherlyClient
    {
        if (null === $this->voucherlyClient) {
            $this->voucherlyClient = $this->createVoucherlyClient($this->getApiKey());
        }

        return $this->voucherlyClient;
    }

    public static function isPaidOrConfirmed(VoucherlyApi\Model\Payment $payment): bool
    {
        return in_array($payment->status, [VoucherlyApi\Enum\PaymentStatus::PAID, VoucherlyApi\Enum\PaymentStatus::CONFIRMED], true);
    }

    private function getApiKey(): string
    {
        return (string) Configuration::get(Configuration::get('VOUCHERLY_SANDBOX') ? 'VOUCHERLY_SAND_KEY' : 'VOUCHERLY_LIVE_KEY');
    }

    private function createVoucherlyClient(string $apiKey): VoucherlyApi\VoucherlyClient
    {
        return new VoucherlyApi\VoucherlyClient([
            'apiKey' => $apiKey,
            'os' => 'PrestaShop',
            'osVersion' => _PS_VERSION_,
            'app' => 'voucherly-prestashop',
            'appVersion' => $this->version,
            'appHouse' => 'Voucherly',
            'deviceType' => 'ECOMMERCE-PLUGIN',
        ]);
    }

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
        Configuration::updateValue('VOUCHERLY_NON_FOOD_CATEGORIES', '[]');
        Configuration::updateValue('VOUCHERLY_SHOW_ICONS', true);

        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
        && $this->registerHook('displayHeader')
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
        Configuration::deleteByName('VOUCHERLY_NON_FOOD_CATEGORIES');
        Configuration::deleteByName('VOUCHERLY_SHOW_ICONS');
        Configuration::deleteByName('VOUCHERLY_TITLE');
        Configuration::deleteByName('VOUCHERLY_DESCRIPTION');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $form = $this->renderForm();

        $this->context->smarty->assign([
            'voucherlyConfigured' => $this->isApiKeyValid(),
            'voucherlyDashboardUrl' => self::DASHBOARD_URL,
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl') . $form;
    }

    private function renderForm()
    {
        if (((bool) Tools::isSubmit('submitVoucherlyModuleRefund')) == true && !empty(Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID'))) {
            $refund = $this->refundVoucherlyPayment((string) Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID'));
            if (!is_numeric($refund)) {
                return $this->renderRefundForm($refund);
            }

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => (int) $refund,
                'vieworder' => '',
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

    private function renderConfigForm($postProcessResult = null)
    {
        $configForm = new HelperForm();

        $configForm->show_toolbar = false;
        $configForm->table = $this->table;
        $configForm->module = $this;
        $configForm->default_form_language = $this->context->language->id;
        $configForm->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $configForm->identifier = $this->identifier;
        $configForm->submit_action = 'submitVoucherlyModuleConfig';
        $configForm->currentIndex = $this->getConfigFormLink();
        $configForm->token = Tools::getAdminTokenLite('AdminModules');

        $configForm->tpl_vars = [
            'fields_value' => [
                'VOUCHERLY_SANDBOX' => Configuration::get('VOUCHERLY_SANDBOX'),
                'VOUCHERLY_LIVE_KEY' => Configuration::get('VOUCHERLY_LIVE_KEY'),
                'VOUCHERLY_SAND_KEY' => Configuration::get('VOUCHERLY_SAND_KEY'),
                'VOUCHERLY_SHIPPING_FOOD' => Configuration::get('VOUCHERLY_SHIPPING_FOOD'),
                'VOUCHERLY_FOOD_CATEGORY' => Configuration::get('VOUCHERLY_FOOD_CATEGORY'),
                'VOUCHERLY_NON_FOOD_CATEGORIES[]' => self::getNonFoodCategoryIds(),
                'VOUCHERLY_SHOW_ICONS' => Configuration::get('VOUCHERLY_SHOW_ICONS'),
                'VOUCHERLY_TITLE' => $this->getLanguageValues('VOUCHERLY_TITLE'),
                'VOUCHERLY_DESCRIPTION' => $this->getLanguageValues('VOUCHERLY_DESCRIPTION'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $categoryOptions = [];
        foreach (Category::getSimpleCategories($this->context->language->id) as $category) {
            $categoryOptions[] = [
                'id_category' => $category['id_category'],
                'name' => $category['name'],
            ];
        }

        return $configForm->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'success' => $postProcessResult['success'] ?? '',
                'error' => $postProcessResult['error'] ?? '',
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key live',
                        'name' => 'VOUCHERLY_LIVE_KEY',
                        'desc' => $this->l('Locate the API key in the developer section of the Voucherly Dashboard.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key sandbox',
                        'name' => 'VOUCHERLY_SAND_KEY',
                        'desc' => $this->l('Locate the API key in the developer section of the Voucherly Dashboard.'),
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
                        'col' => 6,
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->l('Title'),
                        'name' => 'VOUCHERLY_TITLE',
                        'desc' => $this->l('Name of the payment method shown to the customer at checkout. Leave it empty to use the default text.'),
                    ],
                    [
                        'col' => 6,
                        'type' => 'textarea',
                        'lang' => true,
                        'label' => $this->l('Description'),
                        'name' => 'VOUCHERLY_DESCRIPTION',
                        'desc' => $this->l('Text shown to the customer at checkout when the payment method is selected. Leave it empty to use the default text.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Payment method logos'),
                        'name' => 'VOUCHERLY_SHOW_ICONS',
                        'is_bool' => true,
                        'desc' => $this->l('Show the logos of the payment methods at checkout.'),
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
                            'query' => array_merge([['id_category' => '', 'name' => '']], $categoryOptions),
                            'id' => 'id_category',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'multiple' => true,
                        'size' => 8,
                        'label' => $this->l('Categories excluded from meal vouchers'),
                        'desc' => $this->l('Products in these categories are never considered food, even when they also belong to the category for food products. Hold Ctrl to select more than one.'),
                        'name' => 'VOUCHERLY_NON_FOOD_CATEGORIES[]',
                        'id' => 'VOUCHERLY_NON_FOOD_CATEGORIES',
                        'required' => false,
                        'options' => [
                            'query' => $categoryOptions,
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

    private function renderRefundForm($error = '')
    {
        $refundForm = new HelperForm();

        $refundForm->show_toolbar = false;
        $refundForm->table = $this->table;
        $refundForm->module = $this;
        $refundForm->show_cancel_button = true;
        $refundForm->default_form_language = $this->context->language->id;
        $refundForm->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $refundForm->identifier = $this->identifier;
        $refundForm->submit_action = 'submitVoucherlyModuleRefund';
        $refundForm->currentIndex = $this->getRefundFormLink((string) Tools::getValue('p'), [
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ]);
        $refundForm->token = Tools::getAdminTokenLite('AdminModules');

        $refundForm->tpl_vars = [
            'fields_value' => [
                'VOUCHERLY_REFUND_PAYMENT_ID' => Tools::getValue('p'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $refundForm->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Refund'),
                    'icon' => 'icon-credit-card',
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
                    ],
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

        // The saved settings can switch between the sandbox and the live key.
        $this->voucherlyClient = null;

        Configuration::updateValue('VOUCHERLY_SHIPPING_FOOD', Tools::getValue('VOUCHERLY_SHIPPING_FOOD') == '1');
        Configuration::updateValue('VOUCHERLY_FOOD_CATEGORY', Tools::getValue('VOUCHERLY_FOOD_CATEGORY'));
        $nonFoodCategoryIds = Tools::getValue('VOUCHERLY_NON_FOOD_CATEGORIES');
        Configuration::updateValue('VOUCHERLY_NON_FOOD_CATEGORIES', json_encode(array_values(array_map('intval', is_array($nonFoodCategoryIds) ? $nonFoodCategoryIds : []))));
        Configuration::updateValue('VOUCHERLY_SHOW_ICONS', Tools::getValue('VOUCHERLY_SHOW_ICONS') == '1');
        Configuration::updateValue('VOUCHERLY_TITLE', $this->getSubmittedLanguageValues('VOUCHERLY_TITLE'));
        Configuration::updateValue('VOUCHERLY_DESCRIPTION', $this->getSubmittedLanguageValues('VOUCHERLY_DESCRIPTION'));
        // On the first save the per-language keys are new, and Configuration::get() in this request still answers from the cache loaded before them, so the form would show them empty.
        Configuration::loadConfiguration();

        $this->getAndUpdatePaymentGateways();

        return [
            'success' => $this->l('Successfully saved.'),
            'error' => '',
        ];
    }

    /**
     * @return int[]
     */
    public static function getNonFoodCategoryIds(): array
    {
        $categoryIds = json_decode((string) Configuration::get('VOUCHERLY_NON_FOOD_CATEGORIES'), true);

        return is_array($categoryIds) ? array_map('intval', $categoryIds) : [];
    }

    /**
     * @return array<int, string>
     */
    private function getLanguageValues(string $key): array
    {
        $values = [];
        foreach (Language::getLanguages(false) as $language) {
            $values[(int) $language['id_lang']] = (string) Configuration::get($key, (int) $language['id_lang']);
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function getSubmittedLanguageValues(string $key): array
    {
        $values = [];
        foreach (Language::getLanguages(false) as $language) {
            $values[(int) $language['id_lang']] = trim((string) Tools::getValue($key . '_' . (int) $language['id_lang']));
        }

        return $values;
    }

    private function refundVoucherlyPayment(string $paymentId)
    {
        try {
            $payment = $this->getVoucherlyClient()->payments->retrieve($paymentId);
            $orderId = (int) Order::getIdByCartId((int) $payment->metadata['cartId']);
            $order = new Order($orderId);
            if (false === Validate::isLoadedObject($order)) {
                return sprintf($this->l('Payment "%s" has no order.'), $paymentId);
            }

            $refundedStatuses = [VoucherlyApi\Enum\PaymentStatus::REFUNDED, VoucherlyApi\Enum\PaymentStatus::CANCELLED];
            if (!in_array($payment->status, $refundedStatuses, true)) {
                $payment = $this->getVoucherlyClient()->payments->refund($paymentId);
            }

            if (!in_array($payment->status, $refundedStatuses, true)) {
                return sprintf($this->l('Unable to refund Payment "%s".'), $paymentId);
            }

            if ((int) $order->current_state !== (int) Configuration::get('PS_OS_REFUND')) {
                $orderHistory = new OrderHistory();
                $orderHistory->id_order = $orderId;
                $orderHistory->changeIdOrderState((int) Configuration::get('PS_OS_REFUND'), $order, true);
                $orderHistory->add();
            }

            return $orderId;
        } catch (Exception $ex) {
            PrestaShopLogger::addLog('Voucherly: refund of payment ' . $paymentId . ' failed: ' . $ex->getMessage(), 3, $ex->getCode(), null, null, true);

            return sprintf($this->l('Unable to refund Payment "%s".'), $paymentId);
        }
    }

    private function processApiKey($environment): bool
    {
        $optionKey = 'VOUCHERLY_' . strtoupper($environment) . '_KEY';

        $newApiKey = (string) Tools::getValue($optionKey);

        if (!empty($newApiKey) && !$this->isApiKeyValid($newApiKey)) {
            return false;
        }

        Configuration::updateValue($optionKey, $newApiKey);

        return true;
    }

    private function isApiKeyValid(?string $apiKey = null): bool
    {
        $apiKey = $apiKey ?? $this->getApiKey();

        // VoucherlyClient rejects an empty key, which the API would refuse with 401 anyway.
        if ('' === $apiKey) {
            return false;
        }

        try {
            $this->createVoucherlyClient($apiKey)->paymentGateways->list();
        } catch (VoucherlyApi\Exception\ApiException $ex) {
            return 401 !== $ex->getStatusCode();
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    private function getAndUpdatePaymentGateways()
    {
        try {
            $gateways = $this->getPaymentGateways();
        } catch (Exception $ex) {
            // Without a working API key the icons of a previous account must not stay on the checkout.
            $gateways = [];
        }

        Configuration::updateValue('VOUCHERLY_GATEWAYS', json_encode($gateways));
    }

    private function getPaymentGateways()
    {
        $paymentGateways = $this->getVoucherlyClient()->paymentGateways->list()->items ?? [];
        $gateways = [];

        foreach ($paymentGateways as $gateway) {
            if ($gateway->isActive && !$gateway->merchantConfiguration->isFallback) {
                $formattedGateway = [];
                $formattedGateway['id'] = $gateway->id;
                $formattedGateway['name'] = $gateway->name;
                $formattedGateway['type'] = $gateway->type;
                $formattedGateway['src'] = $gateway->icon ?? $gateway->checkoutImage;

                $gateways[] = $formattedGateway;
            }
        }

        return $gateways;
    }

    /**
     * @param string|false $gatewaysJson
     */
    public static function getCheckoutGateways($gatewaysJson): array
    {
        $gateways = json_decode((string) $gatewaysJson);
        if (!is_array($gateways)) {
            return [];
        }

        // Manual payments and merchant-defined methods exist on Voucherly but must not be advertised at checkout.
        return array_values(array_filter($gateways, static function ($gateway) {
            return !in_array($gateway->type ?? '', ['Hidden', 'Custom'], true);
        }));
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->registerStylesheet(
            'voucherly-css',
            'modules/' . $this->name . '/views/css/voucherly-styles.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    public function hookPaymentOptions($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int) $currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return [];
        }

        $idLang = (int) $this->context->language->id;
        $this->smarty->assign([
            'gateways' => Configuration::get('VOUCHERLY_SHOW_ICONS') ? self::getCheckoutGateways(Configuration::get('VOUCHERLY_GATEWAYS')) : [],
            'voucherlyDescription' => (string) Configuration::get('VOUCHERLY_DESCRIPTION', $idLang),
        ]);
        $title = (string) Configuration::get('VOUCHERLY_TITLE', $idLang);

        $options = [];

        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption
            ->setModuleName($this->name)
            ->setCallToActionText('' !== $title ? $title : $this->l('Debit or credit cards, meal vouchers, and other methods — Pay with Voucherly'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_logo.png'))
            ->setAdditionalInformation($this->fetch('module:voucherly/views/templates/front/payment_additional.tpl'));

        $options[] = $paymentOption;

        if (empty($this->context->customer->id)) {
            return $options;
        }

        $voucherlyCustomerId = VoucherlyUsers::getVoucherlyId((int) $this->context->customer->id);
        if (empty($voucherlyCustomerId)) {
            return $options;
        }

        try {
            $params = new VoucherlyApi\Request\ListCustomerPaymentMethodParams();
            $params->length = 100;
            /** @var VoucherlyApi\Model\PaymentMethod[] $customerPaymentMethods */
            $customerPaymentMethods = $this->getVoucherlyClient()->paymentMethods->list($voucherlyCustomerId, $params)->items;
        } catch (Exception $ex) {
            // Saved cards are a shortcut: when Voucherly cannot list them the customer still pays through the main option.
            PrestaShopLogger::addLog('Voucherly: unable to load the saved payment methods: ' . $ex->getMessage(), 2, $ex->getCode(), 'Customer', (int) $this->context->customer->id, true);

            return $options;
        }

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

            if ((int) $card->expirationYear * 100 + (int) $card->expirationMonth < (int) date('Ym')) {
                continue;
            }

            $brand = preg_replace('/[^a-z0-9_-]/', '', Tools::strtolower((string) $card->brand));
            $brandImagePath = _PS_MODULE_DIR_ . $this->name . '/views/img/cards/' . $brand . '.png';
            if ('' === $brand || !file_exists($brandImagePath)) {
                $brandImagePath = _PS_MODULE_DIR_ . $this->name . '/views/img/cards/default.png';
            }

            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option
                ->setModuleName($this->name)
                ->setCallToActionText(Tools::ucfirst((string) $card->brand) . ' ' . $card->pan)
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
        if (empty($orderPayments)) {
            return '';
        }

        $voucherlyId = $orderPayments[0]->transaction_id;

        $voucherlyDashboardLink = $this->getDashboardPaymentLink($voucherlyId);
        $refundFormLink = $this->getRefundFormLink($voucherlyId, [
            'token' => Tools::getAdminTokenLite('AdminModules'),
        ]);

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoImageSrc' => $this->getPathUri() . 'logo.png',
            'voucherlyDashboardLink' => $voucherlyDashboardLink,
            'refundFormLink' => $refundFormLink,
        ]);

        return $this->context->smarty->fetch('module:voucherly/views/templates/admin/displayAdminOrderMainBottom.tpl');
    }

    private function getDashboardPaymentLink(string $paymentId): string
    {
        try {
            $payment = $this->getVoucherlyClient()->payments->retrieve($paymentId);
        } catch (Exception $ex) {
            $payment = null;
        }

        // Payment pages live under the merchant, so without it the link can only open the dashboard home.
        if (empty($payment->merchantId)) {
            return self::DASHBOARD_URL;
        }

        $environment = 'sand' === ($payment->tenant ?? '') ? '/test' : '';

        return self::DASHBOARD_URL . '/' . rawurlencode($payment->merchantId) . $environment . '/pay/payment/details?id=' . rawurlencode($paymentId);
    }

    private function getConfigFormLink()
    {
        return $this->context->link->getAdminLink('AdminModules', false, [], [
            'configure' => $this->name,
        ]);
    }

    private function getRefundFormLink(string $paymentId, array $additionalQueryParameters = [])
    {
        return $this->context->link->getAdminLink('AdminModules', false, [], array_merge($additionalQueryParameters, [
            'configure' => $this->name,
            'p' => $paymentId,
            'form' => 'refund',
        ]));
    }
}
