# Release Notes for Craft Commerce 3.3

### Added
- Added support for partial payments. ([#585](https://github.com/craftcms/commerce/issues/585))
- Carts can now display customer-facing notices on price changes and when items are automatically removed due to going out of stock. ([#2000](https://github.com/craftcms/commerce/pull/2000))
- It’s now possible to set dynamic condition formulas on shipping rules. ([#1959](https://github.com/craftcms/commerce/issues/1959))
- The Orders index page and Edit Order page now have a “Share cart” action, which generates a sharable URL that will load the cart into the user’s session, making it the active cart. ([#1386](https://github.com/craftcms/commerce/issues/1386))
- Shipping rule conditions can now be based on an order’s discounted price, rather than its original price. ([#1948](https://github.com/craftcms/commerce/pull/1948))
- Added the `allowCheckoutWithoutPayment` config setting.
- Added the `allowPartialPaymentOnCheckout` config setting.
- Added the `commerce/cart/complete` action.
- Added `craft\commerce\base\GatewayInterface::supportsPartialPayment()`.
- Added `craft\commerce\base\Gateway::supportsPartialPayment()`.
- Added `craft\commerce\elements\Order::getLoadCartUrl()`.
- Added `craft\commerce\services\Addresses::EVENT_BEFORE_PURGE_ADDRESSES`. ([#1627](https://github.com/craftcms/commerce/issues/1627))
- Added `craft\commerce\services\PaymentCurrencies::convertCurrency()`.
- Added `craft\commerce\test\fixtures\elements\ProductFixture::_getProductTypeIds()`.

### Changed
- Line item descriptions now link to the purchasable’s edit page in the control panel. ([#2048](https://github.com/craftcms/commerce/issues/2048))
- All front-end controllers now support passing the order number via a `number` param. ([#1970](https://github.com/craftcms/commerce/issues/1970))
- Products are now resaved when a product type’s available tax or shipping categories change. ([#1933](https://github.com/craftcms/commerce/pull/1933))

### Deprecated
- Deprecated `craft\commerce\services\Gateways::getGatewayOverrides()` and the `commerce-gateways.php` config file. Gateway-specific config files should be used instead. ([#1963](https://github.com/craftcms/commerce/issues/1963))

### Fixed
- Fixed a PHP 8 compatibility bug. ([#1987](https://github.com/craftcms/commerce/issues/1987))
- Fixed an error that occurred when passing an unsupported payment currency to `craft\commerce\services\PaymentCurrencies::convert()`.
