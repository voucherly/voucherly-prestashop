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

class VoucherlyPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        $request = $this->getPaymentRequest($cart, $customer);
        $payment = \VoucherlyApi\Payment\Payment::create($request);

        $customerMetadata = $this->getCustomerVoucherlyMetadata($customer);
        $customerMetadataKey = self::getVoucherlyCustomerUserMetaKey();
        if (!isset($customerMetadata[$customerMetadataKey]) || $customerMetadata[$customerMetadataKey] != $payment->customerId) {
            $customerMetadata[$customerMetadataKey] = $payment->customerId;

            $customer->voucherly_metadata = json_encode($customerMetadata);
            $customer->save();  
        }

        Tools::redirect($payment->checkoutUrl);
    }

    private static function getVoucherlyCustomerUserMetaKey(): string
    {
        return "id_" . \VoucherlyApi\Api::getEnvironment();
    }

    private function getPaymentRequest(Cart $cart, Customer $customer)
    {
        $metadata = array(
            "cartId" => strval($cart->id)
        )
        
        $request = new \VoucherlyApi\Payment\CreatePaymentRequest;
        $request->metadata = $metadata;
        $request->reference = Tools::passwdGen();
        
        $customerMetadata = $this->getCustomerVoucherlyMetadata($customer);
        $customerMetadataKey = self::getVoucherlyCustomerUserMetaKey();
        if (isset($customerMetadata[$customerMetadataKey]) && !empty($customerMetadata[$customerMetadataKey])) {
            $request->customerId = $customerMetadata[$customerMetadataKey];
        }

        $request->customerFirstName = $customer->firstname;
        $request->customerLastName = $customer->lastname;
        $request->customerEmail = $customer->email;

        $redirectUrl = urldecode($this->context->link->getModuleLink(
            $this->module->name,
            'redirect',
            array(),
            true
        ));
        $request->redirectOkUrl = $redirectUrl;
        $request->redirectKoUrl = $redirectUrl;
        
        $callbackUrl = urldecode($this->context->link->getModuleLink(
            $this->module->name,
            'callback',
            $metadata,
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
    
        $request->lines = $this->getPaymentLines($cart);
        $request->discounts = $this->getPaymentDiscounts($cart);

        return $request;
    }
    
    private function getPaymentLines(Cart $cart)
    {
        $lines = [];

        foreach ($cart->getProducts() as $product) {


            $line = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
            $line->productName = $product['name'];
            $line->productImage = $this->context->link->getImageLink($product['link_rewrite'], $product['id_image'], 'cart_default');
            $line->unitAmount = round($product['price_without_reduction'] * 100);
            if ($product['price_wt']) {
                $line->unitDiscountAmount = round($line->unitAmount - $product['price_wt'] * 100);
            }
            $line->quantity = $product['cart_quantity'];
            $line->isFood = true;

            
            $lines[] = $line;
        }
        
        $shippingAmount = $cart->getTotalShippingCost();
        if ($shippingAmount > 0) {
        
            $carrier = new Carrier($cart->id_carrier);

            $shipping = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
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
                $discount->discountName = $rule["name"];
                $discount->discountDescription = $rule["description"];
                $discount->amount = round($rule['value_real'] * 100);
    
                $discounts[] = $discount;
            }

        }

        return $discounts;
    }

    private function getCustomerVoucherlyMetadata(Customer $customer)
    {
        $metadata = json_decode($customer->voucherly_metadata, true);
        if (is_array($metadata)) {
            return $metadata;
        }

        return [];
    }
}
