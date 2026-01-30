# WIP Release notes for Commerce 5.6

### Store Management
- Added a new "Use Payment Currency Rate Snapshot" store setting. Payment currency rates are now snapshotted when an order is completed, and when this setting is enabled, subsequent partial payments use the snapshotted exchange rates instead of current rates.
- Snapshotted payment currency rates are now displayed in the Transactions tab on order edit pages, with a comparison to current rates.

### Extensibility
- Added `craft\commerce\elements\Order::$paymentCurrencyRates`.
- Added `craft\commerce\elements\Order::setPaymentCurrencyRates()`.
- Added `craft\commerce\elements\db\OrderQuery::$paymentCurrencyRates`.
- Added `craft\commerce\models\Store::getUsesSnapshotPaymentCurrencyRate()`.
- Added `craft\commerce\models\Store::setUsesSnapshotPaymentCurrencyRate()`.