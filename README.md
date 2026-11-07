# Voucherly x PrestaShop


The official [Voucherly][voucherly] PrestaShop plugin.

Requires PrestaShop 1.7.8 to 9.x and PHP 7.4 or later. Download `voucherly-prestashop.zip` from the [latest release][release] and upload it from Modules > Module Manager > Upload a module.

## Changing the order lines

Before the payment is created, the module runs the `actionVoucherlyPaymentLine` hook for each product of the cart. A module registered on it receives `line`, the `VoucherlyApi\Request\PaymentLineRequest` that will be sent, `product`, the row of `Cart::getProducts()`, and `cart`. The line is an object, so changes made to it are kept. For example, to send a product as non-food:

```php
public function hookActionVoucherlyPaymentLine(array $params)
{
    if (123 === (int) $params['product']['id_product']) {
        $params['line']->product->lineType = VoucherlyApi\Enum\LineType::NON_FOOD;
    }
}
```

[voucherly]: https://voucherly.it
[release]: https://github.com/voucherly/voucherly-prestashop/releases/latest
