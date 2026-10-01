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
    /** @var Voucherly */
    public $module;

    public function postProcess()
    {
        $orderLink = $this->context->link->getPageLink('order', true, (int) $this->context->language->id);

        if (!Tools::getIsset('success') || !Tools::getIsset('status') || Tools::getValue('status') == 'Voided') {
            Tools::redirect($orderLink);
            exit;
        }

        $paymentId = '';
        foreach (['paymentId', 'payment_Id', 'p'] as $parameter) {
            if (Tools::getIsset($parameter)) {
                $paymentId = (string) Tools::getValue($parameter);
                break;
            }
        }

        if ('' === $paymentId) {
            Tools::redirect($orderLink);
            exit;
        }

        try {
            $payment = $this->module->getVoucherlyClient()->payments->retrieve($paymentId);
        } catch (Exception $ex) {
            PrestaShopLogger::addLog('Voucherly: redirect unable to read payment ' . $paymentId . ': ' . $ex->getMessage(), 3, $ex->getCode(), null, null, true);

            $this->warning[] = $this->module->l('We could not verify your payment. If you have been charged, please contact customer service.', 'redirect');
            $this->redirectWithNotifications($orderLink);
            exit;
        }

        if (!Voucherly::isPaidOrConfirmed($payment)) {
            $this->warning[] = $this->module->l('An error occurred during the operation. Don\'t worry, the payment has already been reversed. If you need any assistance, please contact customer service.', 'redirect');
            $this->redirectWithNotifications($orderLink);
            exit;
        }

        $orderId = Order::getIdByCartId((int) $payment->metadata['cartId']);
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
