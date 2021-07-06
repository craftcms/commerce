# Release Notes for Craft Commerce 4

## Unreleased

### Added

### Changed
- Craft Commerce now requires Craft CMS 4.0.0-alpha.1 or newer.
- Ajax responses from `commerce/payment-sources/*` no longer include `paymentForm`. Use `paymentFormErrors` instead.

### Deprecated

### Removed (Changed in 4.0, not previously deprecated)

### Removed (Previously Deprecated)

- Removed `craft\commerce\Plugin::t()`. Use `Craft::t('commerce', 'My String')` instead.
- Removed `craft\commerce\elements\Order::getOrderLocale()` has been deprecated. Use `Order::orderLanguage` instead.
- Removed `commerce/orders/purchasable-search` action. Use `commerce/orders/purchasables-table` instead.
- Removed `craft\commerce\elements\Order::getTotalTaxablePrice()`. Taxable price is now calculated within the tax adjuster.
- Removed `craft\commerce\elements\Order::getAdjustmentsTotalByType()` has been deprecated. Use `Order::getTotalTax()`, `Order::getTotalDiscount()`, or `Order::getTotalShippingCost()` instead.
- Removed `craft\commerce\elements\actions\DeleteOrder`. Using standard `craft\elements\actions\Delete` instead.
- Removed `craft\commerce\elements\actions\DeleteProduct`. Using standard `craft\elements\actions\Delete` instead.
- Removed `craft\commerce\elements\Order::getAvailableShippingMethods()` has been deprecated. Use `Order::getAvailableShippingMethodOptions()` instead.
- Removed `craft\commerce\elements\Order::setShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.
- Removed `craft\commerce\elements\Order::getShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.
- Removed `craft\commerce\models\Email::getPdfTemplatePath()`. Use `craft\commerce\models\Email::getPdf()->getTemplatePath()` instead.
- Removed `craft\commmerce\models\LineItem::setSaleAmount()`. Sale amount was read only since 3.1.1.
- Removed `craft\commmerce\models\LineItem::getAdjustmentsTotalByType()` has been deprecated. Use `LineItem::getTax()`, `LineItem::getDiscount()`, or `LineItem::getShippingCost()` instead.
- Removed `Plugin::getInstance()->getPdf()`. Use `Plugin::getInstance()->getPdfs()` instead.
- Removed `craft\commerce\queue\jobs\ConsolidateGuestOrders::consolidate()`.


### Fixed

### Security
