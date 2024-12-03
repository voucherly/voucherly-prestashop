<?php

/**
 * Copyright (C) 2024  Voucherly
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

class VoucherlyRedirectModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // retrieve transaction id of last order by customer context
        $currentCustomerId = $this->context->customer->id;
        $customerOrders = Order::getCustomerOrders($currentCustomerId);
        if (!$customerOrders) {
            redirectToCheckout();
            exit;
        }

        $order = new Order((int) $customerOrders[0]['id_order']);
        if (_PS_VERSION_ >= '8') {
            $orderPayments = $order->getOrderPayments();
        } else {
            $orderPayments = OrderPayment::getByOrderReference($order->reference);
        }

        $paymentId = $orderPayments[0]->transaction_id;
        if (empty($paymentId)) {
            redirectToCheckout();
            exit;
        }

        $payment = VoucherlyApi\Payment\Payment::get($paymentId);
        if (!VoucherlyApi\PaymentHelper::isPaidOrCaptured($payment)) {
            redirectToCheckout();
            exit;
        }

        $customer = new Customer($order->id_customer);

        $confirmationLink = $this->context->link->getPageLink('order-confirmation', true, null, [
            'id_cart' => $order->id_cart,
            'id_order' => $order->id,
            'id_module' => $this->module->id,
            'key' => $customer->secure_key,
        ]);

        Tools::redirect($confirmationLink);
    }

    private function redirectToCheckout()
    {
        $orderLink = $this->context->link->getPageLink('order', true, null);
        Tools::redirect($orderLink);
    }
}
