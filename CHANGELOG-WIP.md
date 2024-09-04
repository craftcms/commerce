# Release Notes for Craft Commerce 5.1 (WIP)

### Store Management
- It’s now possible to manage transfers between inventory locations.
- Catalog pricing rules now support flexible matching based on product and variant conditions. ([#3544](https://github.com/craftcms/commerce/issues/3544))
- Variant conditions can now have an “SKU” rule.

### Administration
- It’s now possible to create custom line items. ([#2301](https://github.com/craftcms/commerce/discussions/2301), [#2233](https://github.com/craftcms/commerce/discussions/2233), [#2345](https://github.com/craftcms/commerce/discussions/2345))
- Added the “Propagation Method” setting to product types. ([#3537](https://github.com/craftcms/commerce/discussions/3537), [#3296](https://github.com/craftcms/commerce/discussions/3296), [#3372](https://github.com/craftcms/commerce/discussions/3372), [#2375](https://github.com/craftcms/commerce/discussions/2375))
- Added “Title Translation Method” settings to product types, for products and variants. ([#3462](https://github.com/craftcms/commerce/issues/3462), [#2466](https://github.com/craftcms/commerce/discussions/2466))
- Added support for selecting products in Link fields.
- Added the “Manage donation settings” permission.
- Added the “Manage inventory transfers” permission.
- Added the “Manage payment currencies” permission.
- Added the “Manage store general setting” permission.
- Added the “Manage subscription plans” permission.

### Extensibility
- Added `craft\commerce\controllers\TransfersController`.
- Added `craft\commerce\elements\Order::EVENT_AFTER_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\elements\Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\elements\Product::$defaultBasePrice`.
- Added `craft\commerce\elements\Product::$storeId`.
- Added `craft\commerce\elements\Product::getCurrencyAttributes()`.
- Added `craft\commerce\elements\Product::getStore()`.
- Added `craft\commerce\elements\Transfer`.
- Added `craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition`.
- Added `craft\commerce\elements\conditions\transfers\TransferCondition`.
- Added `craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition`.
- Added `craft\commerce\elements\db\TransferQuery`.
- Added `craft\commerce\enums\LineItemType`.
- Added `craft\commerce\enums\TransferStatusType`.
- Added `craft\commerce\fieldlayoutelements\TransferManagementField`.
- Added `craft\commerce\models\CatalogPricingRule::getProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::getVariantCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setVariantCondition()`.
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
- Added `craft\commerce\models\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$productTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::$propagationMethod`.
- Added `craft\commerce\models\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\models\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\models\ProductType::getSiteIds()`.
- Added `craft\commerce\models\TransferDetail`.
- Added `craft\commerce\record\TransferDetail`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$productTitleTranslationMethod`.
- Added `craft\commerce\records\ProductType::$propagationMethod`.
- Added `craft\commerce\records\ProductType::$variantTitleTranslationKeyFormat`.
- Added `craft\commerce\records\ProductType::$variantTitleTranslationMethod`.
- Added `craft\commerce\services\CatalogPricing::createCatalogPricesQuery()`
- Added `craft\commerce\services\InventoryLocations::getAllInventoryLocationsAsList`
- Added `craft\commerce\services\LineItems::create()`.
- Added `craft\commerce\services\LineItems::resolveCustomLineItem()`.
- Added `craft\commerce\services\Transfers`.
- Deprecated `craft\commerce\models\LineItem::populateFromPurchasable()`. `populate()` should be used instead.
- Deprecated `craft\commerce\models\LineItem::refreshFromPurchasable()`. `refresh()` should be used instead.
- Deprecated `craft\commerce\services\CatalogPricing::createCatalogPricingQuery()`. `createCatalogPricesQuery()` should be used instead.
- Deprecated `craft\commerce\services\LineItems::createLineItem()`. `create()` should be used instead.
- Removed `craft\commerce\fieldlayoutelements\UserCommerceField`.

### System
- Craft Commerce now requires Craft CMS 5.2 or later.
