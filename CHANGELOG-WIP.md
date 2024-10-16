# Release Notes for Craft Commerce 5.2 (WIP)

### Store Management
- Products can now be structured, per product type.
- It’s now possible for discounts to explicitly require a coupon code. ([#3132](https://github.com/craftcms/commerce/issues/3132))
- New order addresses now default to the store’s country on Order Edit screens. ([#3306](https://github.com/craftcms/commerce/issues/3306))
- Product conditions can now have a “Variant Search” rule. ([#3689](https://github.com/craftcms/commerce/issues/3689))

### Development
- Added the `onPromotion` purchasable query param.
- Added the `onPromotion` GraphQL variant query argument.

### Extensibility
- Added `craft\commerce\console\controllers\UpgradeController::$v3droppableColumns`
- Added `craft\commerce\console\controllers\UpgradeController::EVENT_BEFORE_DROP_V3_DATABASE_ENTITIES`.
- Added `craft\commerce\elements\Product::EVENT_DEFINE_PARENT_SELECTION_CRITERIA`.
- Added `craft\commerce\elements\conditions\products\ProductVariantSearchConditionRule`.
- Added `craft\commerce\elements\db\Purchasable::$onPromotion`.
- Added `craft\commerce\elements\db\Purchasable::onPromotion()`.
- Added `craft\commerce\events\UpgradeEvent`.
- Added `craft\commerce\models\Discount::$requireCouponCode`.
- Added `craft\commerce\models\ProductType::$isStructure`
- Added `craft\commerce\models\ProductType::$maxLevels`
- Added `craft\commerce\models\ProductType::$structureId`
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_BEGINNING`
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_END`
- Added `craft\commerce\models\ProductType::getConfig()`

### System
- Improved the performance of adding items to the cart.
- Improved the performance of shipping rule matching when an order condition formula is used. ([3653](https://github.com/craftcms/commerce/pull/3653))
- Craft Commerce now requires Money PHP 4.2 or later.
- Fixed a bug where outstanding order balances could be calculated incorrectly. ([#3403](https://github.com/craftcms/commerce/issues/3403))
