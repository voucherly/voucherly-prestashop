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

class VoucherlyPaymentModuleFrontController extends ModuleFrontController
{
    /** @var Voucherly */
    public $module;

    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$this->module->active || !$this->isPaymentAllowed() || !Validate::isLoadedObject($cart) || !$cart->nbProducts()
            || !$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice) {
            $this->redirectToCheckout();
        }

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            $this->redirectToCheckout();
        }

        try {
            $request = $this->getPaymentRequest($cart, $customer);
            $payment = $this->module->getVoucherlyClient()->payments->create($request);
        } catch (Exception $ex) {
            PrestaShopLogger::addLog('Voucherly: unable to create the payment: ' . $ex->getMessage(), 3, $ex->getCode(), 'Cart', (int) $cart->id, true);

            $this->warning[] = $this->module->l('An issue has occurred, please try again. If the problem persists, please contact customer service.', 'payment');
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, (int) $this->context->language->id));

            exit;
        }

        // A request property that was never assigned is uninitialized, and reading it directly is an Error.
        if (!empty($payment->customerId) && ($request->customerId ?? null) != $payment->customerId) {
            VoucherlyUsers::create((int) $customer->id, $payment->customerId);
        }

        Tools::redirect($payment->checkoutUrl);
    }

    /**
     * @return never
     */
    private function redirectToCheckout()
    {
        Tools::redirect($this->context->link->getPageLink('order', true, (int) $this->context->language->id));
        exit;
    }

    // The customer can switch payment method, or the merchant restrict this one, after the checkout page was rendered.
    private function isPaymentAllowed(): bool
    {
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === $this->module->name) {
                return true;
            }
        }

        return false;
    }

    private function getPaymentRequest(Cart $cart, Customer $customer)
    {
        $metadata = [
            'cartId' => (string) $cart->id,
        ];

        $request = new VoucherlyApi\Request\CreatePaymentRequest();
        $request->mode = VoucherlyApi\Enum\PaymentMode::PAYMENT;
        $request->referenceId = Tools::passwdGen();

        $voucherlyCustomerId = VoucherlyUsers::getVoucherlyId((int) $customer->id);
        if (!empty($voucherlyCustomerId)) {
            $request->customerId = $voucherlyCustomerId;

            $customerPaymentMethodId = (string) Tools::getValue('pm');
            if (!empty($customerPaymentMethodId)) {
                $request->customerPaymentMethodId = $customerPaymentMethodId;
            }
        }

        $request->customerFirstName = (string) $customer->firstname;
        $request->customerLastName = (string) $customer->lastname;
        $request->customerEmail = (string) $customer->email;

        $redirectUrl = urldecode($this->context->link->getModuleLink(
            $this->module->name,
            'redirect',
            [],
            true
        ));
        $request->redirectOkUrl = $redirectUrl;
        $request->redirectKoUrl = $redirectUrl;

        $callbackUrl = urldecode($this->context->link->getModuleLink(
            $this->module->name,
            'callback',
            [],
            true
        ));
        $request->callbackUrl = $callbackUrl;

        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);

        $address = [
            'address' => $address->address1,
            'zip' => $address->postcode,
            'city' => $address->city,
            'state' => State::getNameById($address->id_state),
            'country' => $address->country,
        ];

        $request->shippingAddress = implode('<br/>', $address);
        $request->country = $country->iso_code ?: null;
        $request->language = Language::getIsoById($this->context->language->id) ?: null;

        $request->metadata = $metadata;

        $request->lines = $this->getPaymentLines($cart);
        $request->discounts = $this->getPaymentDiscounts($cart);

        return $request;
    }

    private function getPaymentLines(Cart $cart)
    {
        $lines = [];

        $foodCategoryId = Configuration::get('VOUCHERLY_FOOD_CATEGORY');

        foreach ($cart->getProducts() as $product) {
            $lineProduct = new VoucherlyApi\Request\PaymentLineRequestProduct();
            $lineProduct->externalId = $product['id_product'];
            $lineProduct->name = (string) $product['name'];
            $lineProduct->variant = (string) $product['attributes'];
            $lineProduct->image = (string) $this->context->link->getImageLink($product['link_rewrite'], $product['id_image'], 'cart_default');
            $lineProduct->taxRate = (float) $product['rate'];

            $line = new VoucherlyApi\Request\PaymentLineRequest();
            $line->unitAmount = (int) round($product['price_without_reduction'] * 100);
            if ($product['price_wt']) {
                $line->unitDiscountAmount = $line->unitAmount - (int) round($product['price_wt'] * 100);
            }
            $line->quantity = (int) $product['cart_quantity'];

            $isFood = true;
            if (!empty($foodCategoryId)) {
                $categorys = Product::getProductCategories($product['id_product']);
                $isFood = in_array($foodCategoryId, $categorys);
            }
            $lineProduct->lineType = $isFood ? VoucherlyApi\Enum\LineType::FOOD : VoucherlyApi\Enum\LineType::NON_FOOD;

            $line->product = $lineProduct;

            $lines[] = $line;
        }

        $shippingAmount = $cart->getTotalShippingCost();
        if ($shippingAmount > 0) {
            $carrier = new Carrier($cart->id_carrier);

            $shippingAmountNetAmount = $cart->getTotalShippingCost(null, false);

            $shippingProduct = new VoucherlyApi\Request\PaymentLineRequestProduct();
            // PrestaShop gives a carrier a new id every time it is edited, while its reference stays the same.
            $shippingProduct->externalId = 'shipping_' . $carrier->id_reference;
            $shippingProduct->name = (string) $carrier->name;
            $shippingProduct->lineType = Configuration::get('VOUCHERLY_SHIPPING_FOOD') ? VoucherlyApi\Enum\LineType::FOOD : VoucherlyApi\Enum\LineType::SHIPPING;
            $shippingProduct->taxRate = $this->calculateTaxRate($shippingAmount - $shippingAmountNetAmount, $shippingAmountNetAmount);

            $shipping = new VoucherlyApi\Request\PaymentLineRequest();
            $shipping->unitAmount = (int) round($shippingAmount * 100);
            $shipping->quantity = 1;
            $shipping->product = $shippingProduct;

            $lines[] = $shipping;
        }

        return $lines;
    }

    private function getPaymentDiscounts(Cart $cart)
    {
        $discounts = [];

        foreach ($cart->getCartRules() as $rule) {
            if ($rule['value_real'] > 0) {
                $discount = new VoucherlyApi\Model\PaymentDiscount();
                $discount->discountName = $rule['name'];
                $discount->discountDescription = $rule['description'];
                $discount->amount = (int) round($rule['value_real'] * 100);

                $discounts[] = $discount;
            }
        }

        return $discounts;
    }

    private function calculateTaxRate($taxAmount, $netAmount)
    {
        if ($netAmount == 0 || $taxAmount == 0) {
            return 0.0;
        }

        return round($taxAmount / $netAmount * 100, 2);
    }
}
