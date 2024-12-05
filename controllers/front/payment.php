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
    public function postProcess()
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        $request = $this->getPaymentRequest($cart, $customer);

        try {
            $payment = VoucherlyApi\Payment\Payment::create($request);
        } catch (VoucherlyApi\NotSuccessException $ex) {
            $this->warning[] = $this->l('An issue has occurred, please try again. If the problem persists, please contact customer service.');

            $choosePaymentMethodUrl = $this->context->link->getPageLink(
                'order',
                true,
                (int) $this->context->language->id
            );
            $this->redirectWithNotifications($choosePaymentMethodUrl);

            exit;
        }

        if ($request->customerId != $payment->customerId) {
            VoucherlyUsers::create($customer->id, $payment->customerId);
        }

        Tools::redirect($payment->checkoutUrl);
    }

    private function getPaymentRequest(Cart $cart, Customer $customer)
    {
        $metadata = [
            'cartId' => (string) $cart->id,
        ];

        $request = new VoucherlyApi\Payment\CreatePaymentRequest();
        $request->referenceId = Tools::passwdGen();

        $voucherlyCustomerId = VoucherlyUsers::getVoucherlyId($customer->id);
        if (isset($voucherlyCustomerId) && !empty($voucherlyCustomerId)) {
            $request->customerId = $voucherlyCustomerId;
        }

        $request->customerFirstName = $customer->firstname;
        $request->customerLastName = $customer->lastname;
        $request->customerEmail = $customer->email;

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
        $request->country = $country->iso_code;
        $request->language = Language::getIsoById($this->context->language->id);

        $request->metadata = $metadata;

        $request->lines = $this->getPaymentLines($cart, $country);
        $request->discounts = $this->getPaymentDiscounts($cart);

        return $request;
    }

    private function getPaymentLines(Cart $cart, Country $country)
    {
        $lines = [];

        $foodCategoryId = Configuration::get('VOUCHERLY_FOOD_CATEGORY', '');

        foreach ($cart->getProducts() as $product) {
            $line = new VoucherlyApi\Payment\CreatePaymentRequestLine();
            $line->productName = $product['name'];
            $line->productImage = $this->context->link->getImageLink($product['link_rewrite'], $product['id_image'], 'cart_default');
            $line->unitAmount = round($product['price_without_reduction'] * 100);
            if ($product['price_wt']) {
                $line->unitDiscountAmount = round($line->unitAmount - $product['price_wt'] * 100);
            }
            $line->quantity = $product['cart_quantity'];
            $line->isFood = true;

            if (isset($foodCategoryId) && !empty($foodCategoryId)) {
                $categorys = Product::getProductCategories($product['id_product']);
                $line->isFood = in_array($foodCategoryId, $categorys);
            }

            // $tax_rule_group = new TaxRuleGroup($product['id_tax_rules_group']);
            // $tax_rate = null;
            // foreach ($tax_rule_group->getRules() as $tax_rule) {
            //     // Check if the tax rule applies to the current zone (this is optional, but useful in multi-zone setups)
            //     if ($tax_rule->id_zone == (int)$country->id_zone) {
            //         $tax_rate = $tax_rule->rate;
            //         break;
            //     }
            // }

            $lines[] = $line;
        }

        $shippingAmount = $cart->getTotalShippingCost();
        if ($shippingAmount > 0) {
            $carrier = new Carrier($cart->id_carrier);

            $shipping = new VoucherlyApi\Payment\CreatePaymentRequestLine();
            $shipping->productName = $carrier->name;
            $shipping->unitAmount = round($shippingAmount * 100);
            $shipping->quantity = 1;
            $shipping->isFood = Configuration::get('VOUCHERLY_SHIPPING_FOOD', false);

            $lines[] = $shipping;
        }

        return $lines;
    }

    private function getPaymentDiscounts(Cart $cart)
    {
        $discounts = [];

        foreach ($cart->getCartRules() as $rule) {
            if ($rule['value_real'] > 0) {
                $discount = new VoucherlyApi\Payment\CreatePaymentRequestDiscount();
                $discount->discountName = $rule['name'];
                $discount->discountDescription = $rule['description'];
                $discount->amount = round($rule['value_real'] * 100);

                $discounts[] = $discount;
            }
        }

        return $discounts;
    }
}
