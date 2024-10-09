# Release Notes for Craft Commerce 4.7 (WIP)

### Store Management
- It’s now possible to specifically make discounts require a coupon code. ([#3132](https://github.com/craftcms/commerce/issues/3132))
- Country code defaults to the store’s country when creating a new address on the Order Edit page. ([#3306](https://github.com/craftcms/commerce/issues/3306))
- Product conditions can now have a “Variant Search” rule. ([#3689](https://github.com/craftcms/commerce/issues/3689))

### Administration

### Development

### Extensibility
- Added `craft\commerce\console\controllers\UpgradeController::EVENT_BEFORE_DROP_V3_DATABASE_ENTITIES`.
- Added `craft\commerce\elements\conditions\products\ProductVariantSearchConditionRule`.
- Added `craft\commerce\models\Discount::$requireCouponCode`.

### System
- Craft Commerce now requires 4.2+ of the moneyphp/money package.
- Fixed a bug where outstanding order balances could be calculated incorrectly. ([#3403](https://github.com/craftcms/commerce/issues/3403))