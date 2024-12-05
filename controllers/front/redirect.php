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

class VoucherlyRedirectModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $orderLink = $this->context->link->getPageLink('order', true, (int) $this->context->language->id);

        $success = Tools::getValue('success');
        $status = Tools::getValue('status');
        if (!isset($success) || !isset($status) || $status == 'Voided') {
            Tools::redirect($orderLink);
            exit;
        }

        $paymentId = Tools::getValue('paymentId');
        if (!isset($paymentId)) {
            $paymentId = Tools::getValue('payment_Id');
            if (!isset($paymentId)) {
                $paymentId = Tools::getValue('p');
                if (!isset($paymentId)) {
                    Tools::redirect($orderLink);
                    exit;
                }
            }
        }

        $payment = VoucherlyApi\Payment\Payment::get($paymentId);
        if (!VoucherlyApi\PaymentHelper::isPaidOrCaptured($payment)) {
            $this->warning[] = $this->l('An error occurred during the operation. Don\'t worry, the payment has already been reversed. If you need any assistance, please contact customer service.');
            $this->redirectWithNotifications($orderLink);
            exit;
        }

        $orderId = Order::getIdByCartId((int) $payment->metadata->cartId);
        $order = new Order($orderId);

        $customer = new Customer($order->id_customer);

        $confirmationLink = $this->context->link->getPageLink('order-confirmation', true, null, [
            'id_cart' => $order->id_cart,
            'id_order' => $order->id,
            'id_module' => $this->module->id,
            'key' => $customer->secure_key,
        ]);

        Tools::redirect($confirmationLink);
    }
}
