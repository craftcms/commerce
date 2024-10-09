# Release Notes for Craft Commerce 5.2 (WIP)

### Store Management

- Added the ability to make a Product Type orderable, so that Products can be manually ordered.

### Administration

### Development
- Added the `onPromotion` purchasable query param.
- Added the `onPromotion` GraphQL variant query argument.

### Extensibility
- Added `craft\commerce\elements\db\Purchasable::$onPromotion`.
- Added `craft\commerce\elements\db\Purchasable::onPromotion()`.
- Added `craft\commerce\models\ProductType::TYPE_CHANNEL`
- Added `craft\commerce\models\ProductType::TYPE_ORDERABLE`
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_BEGINNING`
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_END`
- Added `craft\commerce\models\ProductType::$type`
- Added `craft\commerce\models\ProductType::$structureId`
- Added `craft\commerce\models\ProductType::getConfig()`

### System
