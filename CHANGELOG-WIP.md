# WIP Release notes for Commerce 5.6

- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.

### Store Management
- Added a new "Use Payment Currency Rate Snapshot" store setting. Payment currency rates are now snapshotted when an order is completed, and when this setting is enabled, subsequent payments use the snapshotted exchange rates instead of current rates.
- Snapshotted payment currency rates are now displayed in the Transactions tab on order edit pages, with a comparison to current rates.

### Extensibility
- Added `craft\commerce\elements\Order::$paymentCurrencyRates`.
- Added `craft\commerce\elements\Order::setPaymentCurrencyRates()`.
- Added `craft\commerce\elements\db\OrderQuery::$paymentCurrencyRates`.
- Added `craft\commerce\models\Store::getUsesSnapshotPaymentCurrencyRate()`.
- Added `craft\commerce\models\Store::setUsesSnapshotPaymentCurrencyRate()`.
