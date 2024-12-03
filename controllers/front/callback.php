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

class VoucherlyCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $rawBody = file_get_contents('php://input');
        $params = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($params['paymentId'])) {
            exit('Invalid JSON body');
        }

        $paymentId = $params['paymentId'];
        $payment = VoucherlyApi\Payment\Payment::get($paymentId);
        if (!VoucherlyApi\PaymentHelper::isPaidOrCaptured($payment)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'Payment is not paid or captured',
            ]));
            exit;
        }

        $cartId = $payment->metadata->cartId;
        $cart = new Cart($cartId);
        if (false === Validate::isLoadedObject($cart)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Cart is not loaded',
            ]));
            exit;
        }

        $currency = new Currency($cart->id_currency);
        if (false === Validate::isLoadedObject($currency)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Currency is not loaded',
            ]));
            exit;
        }

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            $this->status = 400;
            $this->ajaxRender(json_encode([
                'ok' => false,
                'error' => 'PrestaShop Customer is not loaded',
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

        if ($cart . OrderExists()) {
            $orderId = Order::getOrderByCartId((int) $cartId);
            $order = new Order($orderId);

            if (_PS_VERSION_ >= '8') {
                $orderPayments = $order->getOrderPayments();
            } else {
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
            }

            if ($orderPayments[0]->transaction_id == $paymentId) {
                $this->ajaxRender(json_encode([
                    'ok' => true,
                    'orderId' => $order->reference,
                ]));
            } else {
                $this->status = 409;
                $this->ajaxRender(json_encode([
                    'ok' => false,
                    'stop' => true,
                    'error' => 'PrestaShop Cart has an order',
                ]));
            }

            exit;
        }

        // set custom order state for Voucherly orders in "pending"
        $this->module->validateOrder($cartId,
            (int) Configuration::get('PS_OS_PAYMENT'),
            $cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName, null,
            [
                'voucherly_environment' => VoucherlyApi\Api::getEnvironment(),
                'transaction_id' => $payment->id,
                'transaction_reference' => $payment->referenceId,
            ],
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
