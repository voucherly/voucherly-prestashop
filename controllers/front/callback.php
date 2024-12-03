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

class VoucherlyCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $rawBody = file_get_contents('php://input');
        $params = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($params['paymentId'])) {
            die('Invalid JSON body');
        }
        
        $paymentId = $params['paymentId'];
        $payment = \VoucherlyApi\Payment\Payment::get($paymentId);
        if (!\VoucherlyApi\PaymentHelper::isPaidOrCaptured($payment)) 
        {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'Payment is not paid or captured'
            ]));
            exit;
        }

        $cartId = $payment->metadata->cartId;
        $cart = new Cart($cartId);
        if (false === Validate::isLoadedObject($cart)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Cart is not loaded'
            ]));
            exit;
        }

        $currency = new Currency($cart->id_currency);
        if (false === Validate::isLoadedObject($currency)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Currency is not loaded'
            ]));
            exit;
        }

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Customer is not loaded'
            ]));
            exit;
        }

        
        /*
         * Restore the context from the $cart_id & the $customer_id to process the validation properly.
         */
        Context::getContext()->cart = $cart;
        Context::getContext()->customer = $customer;
        Context::getContext()->currency = $currency;
        Context::getContext()->language = new Language((int) Context::getContext()->customer->id_lang);

        if ($cart.OrderExists())
        {
            $orderId = Order::getOrderByCartId((int)($cartId));
            $order = new Order($orderId);

            if (_PS_VERSION_ >= '8') {
                $orderPayments = $order->getOrderPayments();
            } else {
                $orderPayments = OrderPayment::getByOrderId($order->id);
            }

            if ($orderPayments[0]->transaction_id == $paymentId) 
            {
                $this->ajaxRender(json_encode([
                    'ok' => true,
                    'orderId' => $order->reference,
                ]));
            }
            else 
            {
                $this->status = 409;
                $this->ajaxRender(json_encode([
                    'ok' => false,
                    'stop' => true,
                    'error' => 'PrestaShop Cart has an order'
                ]));
            }

            exit;
        }

        //set custom order state for Voucherly orders in "pending"
        $this->module->validateOrder($cartId, 
            (int)(Configuration::get('PS_OS_PAYMENT')), 
            $cart->getOrderTotal(true, Cart::BOTH), 
            $this->module->displayName, null, 
            array(
                "voucherly_environment" => \VoucherlyApi\Api::getEnvironment(),
                "transaction_id" => $payment->id,
                "transaction_reference" => $payment->referenceId,
            ), 
            (int) Context::getContext()->currency->id, 
            false, 
            Context::getContext()->customer->secure_key);
            
        
        $order = new Order($this->module->currentOrder);
        
        $this->ajaxRender(json_encode([
            'ok' => true,
            'orderId' => $order->reference,
        ]));

        exit;
    }
}
