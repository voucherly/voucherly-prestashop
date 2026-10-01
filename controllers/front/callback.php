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
    private const LOCK_TIMEOUT_SECONDS = 10;

    /** @var Voucherly */
    public $module;

    public function postProcess()
    {
        $params = json_decode((string) Tools::file_get_contents('php://input'), true);
        if (!is_array($params) || !isset($params['id']) || !is_string($params['id'])) {
            $this->respond([
                'ok' => false,
                'error' => 'Invalid JSON body',
            ]);
        }

        $paymentId = $params['id'];

        try {
            $payment = $this->module->getVoucherlyClient()->payments->retrieve($paymentId);
        } catch (Exception $ex) {
            PrestaShopLogger::addLog('Voucherly: callback unable to read payment ' . $paymentId . ': ' . $ex->getMessage(), 3, $ex->getCode(), null, null, true);

            $this->respond([
                'ok' => false,
                'error' => 'Unable to read the Voucherly payment',
            ]);
        }

        if (!Voucherly::isPaidOrConfirmed($payment)) {
            $this->respond([
                'ok' => false,
                'error' => 'Payment is not paid or captured',
            ]);
        }

        if ($payment->mode != VoucherlyApi\Enum\PaymentMode::PAYMENT) {
            $this->respond([
                'ok' => true,
            ]);
        }

        $cartId = (int) $payment->metadata['cartId'];

        // Voucherly retries the callback when the response is not the expected one, so the same payment can arrive more than once, even concurrently.
        if (!$this->lockCart($cartId)) {
            $this->respond([
                'ok' => false,
                'error' => 'PrestaShop Cart is being processed by another request',
            ]);
        }

        try {
            $response = $this->confirmPayment($payment, $cartId);
        } catch (Exception $ex) {
            PrestaShopLogger::addLog('Voucherly: callback unable to confirm payment ' . $paymentId . ': ' . $ex->getMessage(), 3, $ex->getCode(), 'Cart', $cartId, true);

            $response = [
                'ok' => false,
                'error' => 'Unable to confirm the PrestaShop order',
            ];
        } finally {
            $this->unlockCart($cartId);
        }

        $this->respond($response);
    }

    private function confirmPayment(VoucherlyApi\Model\Payment $payment, int $cartId): array
    {
        $cart = new Cart($cartId);
        if (false === Validate::isLoadedObject($cart)) {
            return [
                'ok' => false,
                'error' => 'PrestaShop Cart is not loaded',
            ];
        }

        $currency = new Currency($cart->id_currency);
        if (false === Validate::isLoadedObject($currency)) {
            return [
                'ok' => false,
                'error' => 'PrestaShop Currency is not loaded',
            ];
        }

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            return [
                'ok' => false,
                'error' => 'PrestaShop Customer is not loaded',
            ];
        }

        if ($cart->orderExists()) {
            $order = new Order((int) Order::getIdByCartId($cartId));

            foreach ($order->getOrderPayments() as $orderPayment) {
                if ($orderPayment->transaction_id === $payment->id) {
                    return [
                        'ok' => true,
                        'orderId' => $order->reference,
                    ];
                }
            }

            return [
                'ok' => false,
                'stop' => true,
                'error' => 'PrestaShop Cart has an order',
            ];
        }

        /*
         * Restore the context from the $cart_id & the $customer_id to process the validation properly.
         */
        Context::getContext()->cart = $cart;
        Context::getContext()->customer = $customer;
        Context::getContext()->currency = $currency;
        Context::getContext()->language = new Language((int) $customer->id_lang);

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('PS_OS_WS_PAYMENT'),
            round(min($payment->paidAmount, $payment->finalAmount) / 100, 2),
            $this->module->displayName,
            null,
            [
                'voucherly_environment' => $payment->tenant,
                'transaction_id' => $payment->id,
                'transaction_reference' => $payment->referenceId,
            ],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $order = new Order((int) $this->module->currentOrder);

        return [
            'ok' => true,
            'orderId' => $order->reference,
        ];
    }

    private function lockCart(int $cartId): bool
    {
        try {
            $acquired = Db::getInstance()->getValue('SELECT GET_LOCK(\'' . pSQL($this->getCartLockName($cartId)) . '\', ' . self::LOCK_TIMEOUT_SECONDS . ')', false);
        } catch (Exception $ex) {
            $acquired = null;
        }

        // Only a timeout means another request holds the lock; databases without GET_LOCK keep the previous unlocked behaviour instead of rejecting every callback.
        return '0' !== (string) $acquired;
    }

    private function unlockCart(int $cartId)
    {
        try {
            Db::getInstance()->getValue('SELECT RELEASE_LOCK(\'' . pSQL($this->getCartLockName($cartId)) . '\')', false);
        } catch (Exception $ex) {
            // The lock also ends with the database connection.
        }
    }

    private function getCartLockName(int $cartId): string
    {
        // MySQL named locks are shared by every database on the server and limited to 64 characters.
        return 'voucherly_cart_' . md5(_DB_NAME_ . _DB_PREFIX_ . $cartId);
    }

    /**
     * @return never
     */
    private function respond(array $response)
    {
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($response));
        exit;
    }
}
