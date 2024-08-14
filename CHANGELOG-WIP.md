# Release Notes for Craft Commerce 5.1 (WIP)

## 5.1.0

### Store Management
- It’s now possible to create custom line items.
- Added the `commerceCustomLineItem()` Twig function.
- Catalog pricing rules now support flexible product and variant matching, based on an product and variant conditions.
- Products now support propagation methods. ([#3537](https://github.com/craftcms/commerce/discussions/3537), [#3296](https://github.com/craftcms/commerce/discussions/3296), [#3372](https://github.com/craftcms/commerce/discussions/3372), [#2375](https://github.com/craftcms/commerce/discussions/2375))
- Products and Variants now support title translations. ([#2466](https://github.com/craftcms/commerce/discussions/2466))
- Added a new “Manage subscription plans” permission.
- Added a new “Manage donation settings” permission.
- Added a new “Manage store general setting” permission.
- Added a new “Manage payment currencies” permission.
- Added a new “Manage inventory transfers” permission.

### Development

### System
- Craft Commerce now requires Craft CMS 5.2 or later.

### Extensibility
- Added `craft\commerce\enums\LineItemType`.
- Added `craft\commerce\helpers\LineItem::generateCustomLineItemHash()`.
- Added `craft\commerce\models\LineItem::$type`.
- Added `craft\commerce\models\LineItem::getHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::getIsPromotable()`.
- Added `craft\commerce\models\LineItem::getIsShippable()`.
- Added `craft\commerce\models\LineItem::getIsTaxable()`.
- Added `craft\commerce\models\LineItem::populate()`.
- Added `craft\commerce\models\LineItem::refresh()`.
- Added `craft\commerce\models\LineItem::setHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::setIsPromotable()`.
- Added `craft\commerce\models\LineItem::setIsShippable()`.
- Added `craft\commerce\models\LineItem::setIsTaxable()`.
- Added `craft\commerce\models\Order::EVENT_AFTER_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\models\Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition`.
- Added `craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition`.
- Added `craft\commerce\models\CatalogPricingRule::getProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::getVariantCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setVariantCondition()`.
- Added `craft\commerce\models\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$productTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::$propagationMethod`.
- Added `craft\commerce\models\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::getSiteIds()`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationMethod`.
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
- Added `craft\commerce\records\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\services\LineItems::create()`.
- Added `craft\commerce\services\LineItems::resolveCustomLineItem()`.
- Deprecated `craft\commerce\models\LineItem::populateFromPurchasable()`. Use `populate()` instead.
- Deprecated `craft\commerce\models\LineItem::refreshFromPurchasable()`. Use `refresh()` instead.
- Deprecated `craft\commerce\services\LineItems::createLineItem()`. Use `create()` instead.
