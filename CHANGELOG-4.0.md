# Release Notes for Craft Commerce 4

## Unreleased

### Added

### Changed
- Craft Commerce now requires Craft CMS 4.0.0-alpha.1 or newer.
- Ajax responses from `commerce/payment-sources/*` no longer include `paymentForm`. Use `paymentFormErrors` instead.

### Deprecated

### Removed

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


### Fixed

### Security
