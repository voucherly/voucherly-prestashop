<?php
/**
* 2007-2024 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2024 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/voucherly-sdk/init.php');

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
        $this->description = $this->l('Accetta buoni pasto con il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità.');
        $this->limited_currencies = array('EUR');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $this->loadConfiguration();
    }

    protected function loadConfiguration()
    {        
        \VoucherlyApi\Api::setApiKey(Configuration::get('VOUCHERLY_LIVE_KEY', ''), "live");
        \VoucherlyApi\Api::setApiKey(Configuration::get('VOUCHERLY_SAND_KEY', ''), "sand");
        \VoucherlyApi\Api::setSandbox(Configuration::get('VOUCHERLY_SANDBOX', false));    

        \VoucherlyApi\Api::setPluginNameHeader('PrestaShop');
        \VoucherlyApi\Api::setPluginVersionHeader($this->version);
        \VoucherlyApi\Api::setPlatformVersionHeader(_PS_VERSION_);
        \VoucherlyApi\Api::setTypeHeader('ECOMMERCE-PLUGIN');
    }
    
    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        
        Configuration::updateValue('VOUCHERLY_SANDBOX', true);
        Configuration::updateValue('VOUCHERLY_LIVE_KEY', '');
        Configuration::updateValue('VOUCHERLY_SAND_KEY', '');
        Configuration::updateValue('VOUCHERLY_SHIPPING_FOOD', true);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('paymentOptions')
            // $this->registerHook('header') &&
            // $this->registerHook('displayBackOfficeHeader') &&
            // $this->registerHook('payment') &&
            // $this->registerHook('paymentReturn') &&
            // $this->registerHook('paymentOptions') &&
            // $this->registerHook('actionPaymentConfirmation') &&
            // $this->registerHook('displayPayment') &&
            ;
    }

    public function uninstall()
    {
        Configuration::deleteByName('VOUCHERLY_SANDBOX');
        Configuration::deleteByName('VOUCHERLY_LIVE_KEY');
        Configuration::deleteByName('VOUCHERLY_SAND_KEY');
        Configuration::deleteByName('VOUCHERLY_SHIPPING_FOOD');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $postProcessConfigResult = null;
        if (((bool)Tools::isSubmit('submitVoucherlyModuleConfig')) == true) {
            $postProcessConfigResult = $this->postProcessConfig();
        }

        $postProcessRefundResult = null;
        if (((bool)Tools::isSubmit('submitVoucherlyModuleRefund')) == true) {
            $postProcessRefundResult = $this->postProcessRefund();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm($postProcessConfigResult, $postProcessRefundResult);
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
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $configForm->token = Tools::getAdminTokenLite('AdminModules');

        $configForm->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );


        $refundForm = new HelperForm();

        $refundForm->show_toolbar = false;
        $refundForm->table = $this->table;
        $refundForm->module = $this;
        $refundForm->default_form_language = $this->context->language->id;
        $refundForm->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $refundForm->identifier = $this->identifier;
        $refundForm->submit_action = 'submitVoucherlyModuleRefund';
        $refundForm->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $refundForm->token = Tools::getAdminTokenLite('AdminModules');

        $refundForm->tpl_vars = array(
            'fields_value' => $this->getRefundFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );


        $configFormSuccess = '';
        $configFormError = '';
        if (!empty($postProcessConfigResult)) {
            $error = $postProcessConfigResult['error'];
            $success = $postProcessConfigResult['success'];

            if (!empty($error)) {
                $configFormError .= $error.'<br />';
            }

            if (!empty($success)) {
                $configFormSuccess = $success;
            }
        }

        $ok = \VoucherlyApi\Api::testAuthentication();
        if ( !$ok ) {
            $configFormError .= sprintf($this->l('Voucherly is not correctly configured, get an API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>').'<br />';
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

        return $configForm->generateForm(array($this->getConfigForm($configFormSuccess, $configFormError))).
            $refundForm->generateForm(array($this->getRefundForm($refundFormSuccess, $refundFormError)));
    }

    protected function getConfigForm($configFormSuccess, $configFormError)
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'success' => $configFormSuccess,
                'error' => $configFormError,
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key live',
                        'name' => 'VOUCHERLY_LIVE_KEY',
                        'desc' => sprintf($this->l('Locate API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => 'API key sandbox',
                        'name' => 'VOUCHERLY_SAND_KEY',
                        'desc' => sprintf($this->l('Locate API key in developer section on %sVoucherly Dashboard%s.'), '<a href="https://dashboard.voucherly.it" target="_blank">', '</a>'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Sandbox Mode'),
                        'name' => 'VOUCHERLY_SANDBOX',
                        'is_bool' => true,
                        'desc' => $this->l('Sandbox Mode can be used to test payments.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Shipping as food'),
                        'name' => 'VOUCHERLY_SHIPPING_FOOD',
                        'is_bool' => true,
                        'desc' => $this->l('If shipping is considered food, the customer can pay for it with meal vouchers.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'VOUCHERLY_SANDBOX' => Configuration::get('VOUCHERLY_SANDBOX', false),
            'VOUCHERLY_LIVE_KEY' => Configuration::get('VOUCHERLY_LIVE_KEY', ''),
            'VOUCHERLY_SAND_KEY' => Configuration::get('VOUCHERLY_SAND_KEY', ''),
            'VOUCHERLY_SHIPPING_FOOD' => Configuration::get('VOUCHERLY_SHIPPING_FOOD', false),
        );
    }

    protected function getRefundForm($refundFormSuccess, $refundFormError)
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Refund'),
                ),
                'success' => $refundFormSuccess,
                'error' => $refundFormError,
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Payment ID'),
                        'name' => 'VOUCHERLY_REFUND_PAYMENT_ID',
                        'desc' => $this->l('Get the Payment ID from Order details > Payment > Transaction ID.')
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Amount'),
                        'name' => 'VOUCHERLY_REFUND_AMOUNT',
                        'desc' => $this->l('Leave empty to refund the total amount.').'<br />'.$this->l('Decimals must be divided with a dot.')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Refund'),
                ),
            ),
        );
    }

    protected function getRefundFormValues()
    {
        return array(
            'VOUCHERLY_REFUND_PAYMENT_ID' => Configuration::get('VOUCHERLY_REFUND_PAYMENT_ID', ''),
            'VOUCHERLY_REFUND_AMOUNT' => Configuration::get('VOUCHERLY_REFUND_AMOUNT', ''),
        );
    }

    protected function postProcessConfig()
    {        
        $liveOk = $this->processApiKey("live");
        if (!$liveOk) {
            return array(
                'success' => '',
                'error' => sprintf($this->l('The "%s" is invalid.'), 'API key live'),
            );
        }

        $sandOk = $this->processApiKey("sand");
        if (!$sandOk) {
            return array(
                'success' => '',
                'error' => sprintf($this->l('The "%s" is invalid.'), 'API key sandbox'),
            );
        }

        $postedSandbox = Tools::getValue('VOUCHERLY_SANDBOX') == '1';
        Configuration::updateValue('VOUCHERLY_SANDBOX', $postedSandbox);

        \VoucherlyApi\Api::setSandbox($postedSandbox);

        Configuration::updateValue('VOUCHERLY_SHIPPING_FOOD', Tools::getValue('VOUCHERLY_SHIPPING_FOOD') == '1');        

        return array(
            'success' => $this->l('Successfully saved.'),
            'error' => '',
        );
    }
    
    
    private function processApiKey($environment): bool
    {
        $optionKey = 'VOUCHERLY_' . strtoupper($environment) . '_KEY';

        $apiKey = Configuration::get($optionKey, '');
        $newApiKey = Tools::getValue($optionKey);

        if (!empty($newApiKey)) {
            
            $ok = \VoucherlyApi\Api::testAuthentication($newApiKey);
            if ( !$ok ) {
                return false;
            }

        }
        
        Configuration::updateValue($optionKey, $newApiKey);

        \VoucherlyApi\Api::setApiKey($newApiKey, $environment);

        // Should I delete user metadata?

        return true;
    }

    protected function postProcessRefund()
    {
        $postedRefundPaymentId = Tools::getValue('VOUCHERLY_REFUND_PAYMENT_ID');
        $postedRefundAmount = Tools::getValue('VOUCHERLY_REFUND_AMOUNT');

        if (empty($postedRefundPaymentId)) {
            return array();
        }

        try {
            $refund = array(
                'flow' => 'REFUND',
                'currency' => 'EUR',
                'parent_payment_uid' => $postedRefundPaymentId
            );

            if ($postedRefundAmount != '') {
                $refund['amount_unit'] = $postedRefundAmount * 100;
            }

            \VoucherlyGBusiness\Payment::create($refund);
        } catch (\Exception $ex) {
            return array(
                'success' => '',
                'error' => sprintf($this->l('Unable to refund Payment "%s".'), $postedRefundPaymentId),
            );
        }

        return array(
            'success' => sprintf($this->l('Successfully refunded Payment "%s".'), $postedRefundPaymentId),
            'error' => '',
        );
    }

    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookPaymentOptions($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
            return false;
        }

        $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption
            ->setCallToActionText($this->l('Pay with Voucherly'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/payment_logo.png'));

        return array($paymentOption);
    }
}
