# WIP Release Notes for Craft Commerce

### Store Management

### Administration

### Development
- Orders now have a `dateFirstPaid` property that records the date and time when the order was first paid in full.

### Extensibility
- Added `craft\commerce\elements\Order::$dateFirstPaid`.
- Added `craft\commerce\elements\db\OrderQuery::$dateFirstPaid`.
- Added `craft\commerce\elements\db\OrderQuery::dateFirstPaid()`.
- Added `craft\commerce\records\Order::$dateFirstPaid`.

### System