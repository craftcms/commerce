# Release Notes for Craft Commerce

## Unreleased 3.3

### Added
- It’s now possible to accept partial payments on orders. ([#585](https://github.com/craftcms/commerce/issues/585))
- Carts now have customer notifications when something has changed on an order, like an item removed automatically due to out of stock, or a price changed since last viewing the order. ([#2000](https://github.com/craftcms/commerce/pull/2000))
- It’s now possible to set dynamic condition formulas on shipping rules. ([#1959](https://github.com/craftcms/commerce/issues/1959))
- Added a “Copy load cart URL” action to the Orders index page. ([#1386](https://github.com/craftcms/commerce/issues/1386))
- It’s now possible to include the discounted order value when calculating the order total conditions in a shipping rule. ([#1948](https://github.com/craftcms/commerce/pull/1948))
- It's now possible to click the line item description on the Order Edit page to navigate to the purchasable’s CP edit page. ([#2048](https://github.com/craftcms/commerce/issues/2048))
- Added the `allowCheckoutWithoutPayment` config setting.
- Added the `allowPartialPaymentOnCheckout` config setting.
- Added `craft\commerce\test\fixtures\elements\ProductFixture::_getProductTypeIds()`.
- Added `craft\commerce\elements\Order::getLoadCartUrl()`.
- Added `craft\commerce\services\Addresses::EVENT_BEFORE_PURGE_ADDRESSES`. ([#1627](https://github.com/craftcms/commerce/issues/1627))
- Added `\craft\commerce\base\GatewayInterface::supportsPartialPayment()`.
- Added `craft\commerce\base\Gateway::supportsPartialPayment()`.
- Added `craft\commerce\services\PaymentCurrencies::convertCurrency()`.

### Changed
- All front end controllers now use the `number` param for passing the order number. ([#1970](https://github.com/craftcms/commerce/issues/1970))
- All products are now resaved when a product types’ available tax or shipping categories change. ([#1933](https://github.com/craftcms/commerce/pull/1933))

### Deprecated
- Using the `commerce-gateways.php` config file is deprecated. Use the gateway’s config file instead. ([#1963](https://github.com/craftcms/commerce/issues/1963))
- Deprecated `\craft\commerce\services\Gateways::getGatewayOverrides()`.

### Fixed
- Fixed a PHP 8 bug in `\craft\commerce\models\Address::addressLines()`. ([#1987](https://github.com/craftcms/commerce/issues/1987))
- Fixed a bug in `craft\commerce\test\fixtures\elements\ProductFixture` caused by product type memoization. ([#2003](https://github.com/craftcms/commerce/issues/2003))
- Fixed a PHP error that occurred when using `craft\commerce\services\PaymentCurrencies::convert()` with a payment currency that doesn’t exist.