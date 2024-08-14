# Release Notes for Craft Commerce 5.1 (WIP)

## Unreleased 5.1


### Administration

- Added the ability to manage transfers between inventory locations.
- Products now support propagation methods. ([#3537](https://github.com/craftcms/commerce/discussions/3537), [#3296](https://github.com/craftcms/commerce/discussions/3296), [#3372](https://github.com/craftcms/commerce/discussions/3372), [#2375](https://github.com/craftcms/commerce/discussions/2375))
- Products and Variants now support title translations. ([#2466](https://github.com/craftcms/commerce/discussions/2466))
- Added a new “Manage subscription plans” permission.
- Added a new “Manage donation settings” permission.
- Added a new “Manage store general setting” permission.
- Added a new “Manage payment currencies” permission.
- Added a new “Manage inventory transfers” permission.

### System
- Craft Commerce now requires Craft CMS 5.2 or later.

### Extensibility
- Added `craft\commerce\models\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$productTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$propagationMethod`.
- Added `craft\commerce\models\ProductType::getSiteIds()`.
- Added `craft\commerce\records\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\records\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationMethod`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$propagationMethod`.
- Removed `craft\commerce\fieldlayoutelements\UserCommerceField`.
- Added `\craft\commerce\controllers\TransfersController`.
- Added `craft\commerce\elements\Transfer`.
- Added `craft\commerce\elements\conditions\transfers\TransferCondition`.
- Added `craft\commerce\elements\db\TransferQuery`.
- Added `craft\commerce\enums\TransferStatusType`.
- Added `craft\commerce\fieldlayoutelements\TransferManagementField`.
- Added `craft\commerce\models\TransferDetail`.
- Added `craft\commerce\record\TransferDetail`.
- Added `craft\commerce\services\InventoryLocations::getAllInventoryLocationsAsList`
- Added `craft\commerce\services\Transfers`.
