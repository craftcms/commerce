# Release Notes for Craft Commerce 4.2 (WIP)

## 4.2.0 - Unreleased

### Added
- `commerce/cart/*` actions now return `shippingAddress` and `billingAddress` values in JSON responses. ([#2921](https://github.com/craftcms/commerce/issues/2921))
- Added `craft\commerce\behaviors\CustomerBehavoir::getPrimaryPaymentSource()`.
- Added `craft\commerce\behaviors\CustomerBehavoir::getPrimaryPaymentSourceId()`.
- Added `craft\commerce\behaviors\CustomerBehavoir::setPrimaryPaymentSourceId()`.
- Added `craft\commerce\controllers\PaymentSourcesController::actionSetPrimaryPaymentSource()`.
- Added `craft\commerce\elements\conditions\customers\HasOrdersInDateRange`.
- Added `craft\commerce\elements\conditions\customers\HasOrdersInLastPeriod`.
- Added `craft\commerce\elements\conditions\orders\ItemTotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\OrderTextValuesAttributeConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ReferenceConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalPriceConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalQtyConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$totalQty`.
- Added `craft\commerce\elements\db\OrderQuery::totalPrice()`.
- Added `craft\commerce\elements\db\OrderQuery::totalQty()`.
- Added `craft\commerce\elements\Order::$storedTotalQty`.
- Added `craft\commerce\elements\Order::autoSetPaymentSource()`.
- Added `craft\commerce\models\PaymentSource::getIsPrimary()`.
- Added `craft\commerce\models\Settings::$autoSetPaymentSource`.
- Added `craft\commerce\records\Customer::$primaryPaymentSourceId`.
- Added `craft\commerce\services\savePrimaryPaymentSourceId()`.

### Changed
- It is now possible to set a primary payment source for a customer.
- It is now possible to automatically set a customer’s primary payment source on new carts using the `autoSetPaymentSource` config setting.
- It is now possible to query orders by `itemTotal` and `totalQty`.
- Querying an order by `reference` now allows a wider range of inputs.
- Shipping and Tax Categories are now archived instead of deleted.
- It is now possible to define how addresses are matched in `Order::hasMatchingAddresses()`.
- Update order status action now returns relevant flash messages on completion.
