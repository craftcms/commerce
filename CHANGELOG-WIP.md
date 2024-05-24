# Release Notes for Craft Commerce 5.1 (WIP)

## 5.1.0

### Store Management

- It’s now possible to create custom line items.

### Administration

### Development

### Extensibility

- Added `craft\commerce\enums\LineItemType`.
- Added `craft\commerce\models\LineItem::$type`.
- Added `craft\commerce\models\LineItem::populate()`.
- Added `craft\commerce\models\LineItem::refresh()`.
- Added `craft\commerce\models\LineItem::getHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::setHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::getIsPromotable()`.
- Added `craft\commerce\models\LineItem::setIsPromotable()`.
- Deprecated `craft\commerce\models\LineItem::populateFromPurchasable()`. Use `populate()` instead.
- Deprecated `craft\commerce\models\LineItem::refreshFromPurchasable()`. Use `refresh()` instead.
