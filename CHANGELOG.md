# Changelog

## [2.1.0] - 2026-10-01

- Requires PrestaShop 1.7.8 or later (PHP 7.4 or later), tested up to PrestaShop 9.2
- Update voucherly/voucherly-php-sdk to 2.0.0
- Make the payment callback idempotent when Voucherly delivers the same payment more than once, even concurrently, so a cart never gets two orders
- Fix the paid amount of orders of 1,000 € or more, which were created in "Payment error" status
- Fix fatal error when saving the settings without a valid API key
- Fix fatal errors when the Voucherly API cannot be reached at checkout, on return from Voucherly and in the payment callback
- Fix the Voucherly payment option with the One Page Checkout of PrestaShop 9.2
- Hide manual and custom payment methods from the checkout icons
- Hide expired saved cards
- Show saved card logos at the same size as the Voucherly logo
- Link the order page to the payment on the new Voucherly Dashboard address
- Fix PHP 8.2+ deprecation notice
- Fix Italian translation of the checkout error messages
- PrestaShop validator fixes

## [2.0.2] - 2026-02-10

- Fix checkout as a guest customer

## [2.0.1] - 2025-02-17

- Refactor Voucherly icon's style

## [2.0.0] - 2025-01-13

- Added "Shipping as food" option
- Display payment gateways' icons
- Pay with customer payment methods
- Added "Category for food products" option
