# Release Notes for Craft Commerce

## 5.3.4 - 2025-02-26

- Fixed a bug where discounts weren’t applying if an order was recalculated more than once in the same request. ([#3896](https://github.com/craftcms/commerce/issues/3896))
- Fixed a bug where shipping rules weren’t saving their shipping category conditions in non-primary stores. ([#3851](https://github.com/craftcms/commerce/issues/3851))
- Fixed a PHP error that could occur on order completion, for orders with disabled variants in the non-primary store.
- Fixed a bug where Edit Gateway pages were showing duplicate settings.

## 5.3.3 - 2025-02-19

- Fixed a bug where line item totals could be formatted in the wrong currency on Edit Order pages. ([#3891](https://github.com/craftcms/commerce/issues/3891)) 
- Fixed a bug where email and order status change events weren’t getting triggered in non-primary stores.

## 5.3.2.2 - 2025-02-10

- Fixed a bug where carts’ adjustment totals could be calculated incorrectly. ([#3888](https://github.com/craftcms/commerce/issues/3888))
- Fixed a styling issue on the Edit Order page. ([#3889](https://github.com/craftcms/commerce/issues/3889))

## 5.3.2.1 - 2025-02-07

- Fixed a bug where tax and shipping totals weren’t being recalculated in `commerce/cart/*` action requests. ([#3888](https://github.com/craftcms/commerce/issues/3888))

## 5.3.2 - 2025-02-06

- Added `craft\commerce\collections\InventoryMovementCollection::getPurchasables()`.
- Added `craft\commerce\base\Gateway::transactionSupportsRefund()`.
- Fixed a bug where product variants weren’t getting restored when soft-deleted products were restored. ([#3815](https://github.com/craftcms/commerce/issues/3815))
- Fixed a bug where purchables’ cached per-store stock totals weren’t getting updated when inventory was moved.
- Fixed a bug where it wasn’t possible to update inventory transfers that contained deleted inventory items.
- Fixed a bug where the `commerce/cart/update-cart` action could return unnecessary validation errors. ([3873](https://github.com/craftcms/commerce/issues/3873))

## 5.3.1 - 2025-02-03

- Improved logging when a user deletion is prevented due to the user having Commerce orders. ([#3686](https://github.com/craftcms/commerce/issues/3686))
- Fixed a PHP error that could occur when calculating tax adjustments. ([#3822](https://github.com/craftcms/commerce/issues/3822))
- Fixed a PHP error that could occur when updating an order’s status from the CLI. ([#3858](https://github.com/craftcms/commerce/issues/3858))
- Fixed a bug where additional tax ID validators where not being checked when a store’s “Validate Business Tax ID as Vat ID” setting was enabled.
- Fixed a bug where the deprecated `craft\commerce\models\TaxRate::$isVat` property was still being set. ([#3874](https://github.com/craftcms/commerce/issues/3874))
- Fixed a bug where emails could be rendered in the wrong language when sent manually from the control panel. ([#3852](https://github.com/craftcms/commerce/issues/3852))
- Fixed an error that occurred when rendering a Link field with a product selected on the front end. ([#3833](https://github.com/craftcms/commerce/issues/3833))

## 5.3.0.2 - 2025-01-31

- Fixed a bug where gateways weren’t ensuring handle uniqueness. ([#3839](https://github.com/craftcms/commerce/issues/3839))

## 5.3.0.1 - 2025-01-30

- Fixed a bug where the `craft\commerce\events\CartPurgeEvent` could not be used in any event. ([#2721](https://github.com/craftcms/commerce/issues/2721)) 
- Added `craft\commerce\services\Carts::EVENT_BEFORE_PURGE_INACTIVE_CARTS`. ([#3684](https://github.com/craftcms/commerce/discussions/3684))

## 5.3.0 - 2025-01-30

### Store Management
- Archived gateways are now listed on the Gateways index page. ([#3839](https://github.com/craftcms/commerce/issues/3839))
- Added card view designers for products and variants. ([#3809](https://github.com/craftcms/commerce/pull/3809))
- Order conditions can now have “Coupon Code” and “Payment Gateway” rules. ([#3776](https://github.com/craftcms/commerce/discussions/3776), [#3722](https://github.com/craftcms/commerce/discussions/3722))
- Product variant conditions can now have a “Product” rule.
- Tax rates now have statuses. ([#3790](https://github.com/craftcms/commerce/discussions/3790))
- It’s now possible to restore soft-deleted product variants.
- Improved Craft Commerce navigation and breadcrumb labels.
- Added an “Allow out of stock purchases” option to purchasables. ([#3649](https://github.com/craftcms/commerce/discussions/3649))

### Administration
- The “Recipient”, “BCC’d Recipient”, and “CC’d Recipient” email settings now support being set to environment variables. ([#3738](https://github.com/craftcms/commerce/issues/3738))
- It’s now possible to view (but not edit) system and plugin settings on environments where `allowAdminChanges` is disabled.

### Development
- Added the `couponCode` order query param.
- Orders’ `makePrimaryShippingAddress` and `makePrimaryBillingAddress` property values now persist during checkout.
- The `commerce/update-cart` action now includes an `originalCart` key in JSON responses. ([#430](https://github.com/craftcms/commerce/issues/430))

### Extensibility
- Added support for registering custom tax ID validators.
- Added `craft\commerce\base\InventoryItemTrait`.
- Added `craft\commerce\base\InventoryItemTrait`.
- Added `craft\commerce\base\InventoryLocationTrait`.
- Added `craft\commerce\base\InventoryLocationTrait`.
- Added `craft\commerce\base\Purchasable::hasInventory()`.
- Added `craft\commerce\base\Purchasable::loadSales()`.
- Added `craft\commerce\base\TaxIdValidatorInterface`.
- Added `craft\commerce\controllers\BaseStoreManagementController::getStoreSwitch()`.
- Added `craft\commerce\elements\Purchasable::$allowOutOfStockPurchases`.
- Added `craft\commerce\elements\Purchasable::getIsOutOfStockPurchasingAllowed()`.
- Added `craft\commerce\elements\conditions\orders\CouponCodeConditionRule`.
- Added `craft\commerce\elements\conditions\variants\ProductConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$couponCode`.
- Added `craft\commerce\elements\db\OrderQuery::couponCode()`.
- Added `craft\commerce\events\CartPurgeEvent`.
- Added `craft\commerce\events\PurchasableOutOfStockPurchasesAllowedEvent`.
- Added `craft\commerce\services\Gateways\getAllArchivedGateways()`.
- Added `craft\commerce\services\Inventory::updateInventoryLevel()`.
- Added `craft\commerce\services\Inventory::updatePurchasableInventoryLevel()`.
- Added `craft\commerce\services\Purchasables::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED`.
- Added `craft\commerce\services\Purchasables::isPurchasableOutOfStockPurchasingAllowed()`.
- Added `craft\commerce\services\Taxes::EVENT_REGISTER_TAX_ID_VALIDATORS`.
- Added `craft\commerce\services\Taxes::getEnabledTaxIdValidators()`.
- Added `craft\commerce\services\Taxes::getTaxIdValidators()`.
- Added `craft\commerce\taxidvalidators\EuVatIdValidator`.

### System
- Craft Commerce now requires Craft CMS 5.6.0 or later.
- Fixed a bug where orders’ promotional prices could be calculated incorrectly when using sales.
- Fixed a bug where the `commerce/cart/update-cart` action wasn’t respecting `makePrimaryShippingAddress` and `makePrimaryBillingAddress` params for newly-created addresses. ([#3864](https://github.com/craftcms/commerce/pull/3864))
- Fixed a PHP error that could occur when viewing discounts. ([#3844](https://github.com/craftcms/commerce/issues/3844))

## 5.2.12.1 - 2025-01-23

- Fixed a JavaScript error that occurred when updating an order’s status for a non-primary store on order indexes. 

## 5.2.12 - 2025-01-22

- Fixed a bug where product types’ “Max Variants” settings weren’t being respected. ([#3845](https://github.com/craftcms/commerce/issues/3845))
- Fixed a bug where products could be duplicated by users without the “Create products” permission for the product type. ([#3838](https://github.com/craftcms/commerce/issues/3838))
- Fixed a PHP error that could occur when updating a cart. ([#3842](https://github.com/craftcms/commerce/issues/3842))
- Fixed a PHP error that could occur when adding an invalid address to a cart. ([#3848](https://github.com/craftcms/commerce/issues/3848))

## 5.2.11 - 2025-01-02

- Fixed an error that occurred when rendering a Link field with a product selected on the front end. ([#3833](https://github.com/craftcms/commerce/issues/3833))

## 5.2.10 - 2024-12-18

- Fixed a PHP error that could occur when eager-loading variants’ owners. ([#3817](https://github.com/craftcms/commerce/issues/3817))
- Fixed a bug where variant chips weren’t rendering correctly. ([#3813](https://github.com/craftcms/commerce/issues/3813))

## 5.2.9.1 - 2024-12-13

- Fixed a bug where line item promotional prices weren’t updated when upgrading to Commerce 5.

## 5.2.9 - 2024-12-11

- Fixed a PHP error that could occur when updating the inventory of a purchasable for a non-primary site. ([#3788](https://github.com/craftcms/commerce/issues/3788))
- Fixed a PHP error that occurred when making a partial payment on an order from the control panel. ([#3804](https://github.com/craftcms/commerce/issues/3804))
- Fixed a PHP error that could occur when calculating order totals. ([#3802](https://github.com/craftcms/commerce/issues/3802)) 
- Fixed a bug where product indexes weren’t always showing the correct price. ([#3807](https://github.com/craftcms/commerce/issues/3807))
- Fixed a bug where changes to inline-editable Matrix fields weren’t getting saved for product variants. ([#3805](https://github.com/craftcms/commerce/issues/3805))
- Fixed a bug where the Edit Order page wasn’t showing order errors.

## 5.2.8 - 2024-12-04

- Fixed a bug where line items weren’t getting hyperlinked within Edit Order pages. ([#3792](https://github.com/craftcms/commerce/issues/3792))
- Fixed a bug where Inventory pages were showing draft purchasables.
- Fixed a PHP error that could occur when creating inventory transfers. ([#3696](https://github.com/craftcms/commerce/issues/3696))
- Fixed a bug where prices weren’t getting formatted per the user’s formatting locale, in payment models on Edit Order pages. ([#3789](https://github.com/craftcms/commerce/issues/3789))
- Fixed a bug where store settings weren’t respecting environment variables. ([#3786](https://github.com/craftcms/commerce/issues/3786))

## 5.2.7 - 2024-12-02

- Fixed an error that occurred on the Orders index page when running Craft CMS 5.5.4 or later. ([#3793](https://github.com/craftcms/commerce/issues/3793))
- Fixed a bug where a structured product type’s “Max Levels” setting wasn’t being respected. ([#3785](https://github.com/craftcms/commerce/issues/3785))
- Fixed an information disclosure vulnerability.

## 5.2.6 - 2024-11-26

- Fixed a bug where variant prices could be displayed incorrectly when inline editing. ([#3768](https://github.com/craftcms/commerce/issues/3768))
- Fixed a performance degradation bug with variant queries. ([#3758](https://github.com/craftcms/commerce/issues/3758))
- Fixed a PHP error that could occur when managing store settings. ([#3780](https://github.com/craftcms/commerce/issues/3780))

## 5.2.5 - 2024-11-20

- The `resave/products`, `resave/orders`, and `resave/carts` commands now support the `--with-fields` option.
- Fixed a SQL error that could occur when updating. ([#3778](https://github.com/craftcms/commerce/issues/3778))

## 5.2.4 - 2024-11-14

- Improved the performance of `craft\commerce\elements\Product::getVariants()`. ([#3578](https://github.com/craftcms/commerce/issues/3758))
- Fixed a SQL error that could occur when creating a variant. ([#3763](https://github.com/craftcms/commerce/issues/))

## 5.2.3 - 2024-11-13

- Fixed a performance degradation bug with variant queries. ([#3758](https://github.com/craftcms/commerce/issues/3758))
- Fixed a bug where it was possible to select purchasables that didn’t belong to an order’s site, from Edit Order pages. ([#3756](https://github.com/craftcms/commerce/issues/3756))

## 5.2.2.1 - 2024-11-08

- Fixed a PHP error that could occur when retrieving a variant. ([#3754](https://github.com/craftcms/commerce/issues/3754))

## 5.2.2 - 2024-11-06

- Fixed a bug where product revisions weren’t storing variant relations.
- Fixed a PHP error that occurred when calling a product or variant’s `render()` method. ([#3742](https://github.com/craftcms/commerce/issues/3742))
- Fixed a bug where inventory data wasn’t getting saved when creating a new variant. ([#3661](https://github.com/craftcms/commerce/issues/3661))

## 5.2.1 - 2024-10-23

- Fixed a bug where the Commerce subnav could be missing the “Product” nav item. ([#3735](https://github.com/craftcms/commerce/issues/3735))
- Fixed PHP errors that could occur when completing an order. ([#3733](https://github.com/craftcms/commerce/issues/3733), [#3736](https://github.com/craftcms/commerce/issues/3736))

## 5.2.0 - 2024-10-16

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
- Added `craft\commerce\models\ProductType::$isStructure`.
- Added `craft\commerce\models\ProductType::$maxLevels`.
- Added `craft\commerce\models\ProductType::$structureId`.
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_BEGINNING`.
- Added `craft\commerce\models\ProductType::DEFAULT_PLACEMENT_END`.
- Added `craft\commerce\models\ProductType::getConfig()`.

### System
- Improved the performance of adding items to the cart.
- Improved the performance of shipping rule matching when an order condition formula is used. ([3653](https://github.com/craftcms/commerce/pull/3653))
- Craft Commerce now requires Money PHP 4.2 or later.
- Fixed a bug where outstanding order balances could be calculated incorrectly. ([#3403](https://github.com/craftcms/commerce/issues/3403))

## 5.1.4 - 2024-10-16

- Fixed a PHP error that could occur when creating a subscription. ([#3710](https://github.com/craftcms/commerce/issues/3710))
- Fixed a bug where inventory items could appear with blank descriptions on the Inventory management screen. ([#3706](https://github.com/craftcms/commerce/issues/3706))
- Fixed a bug where additional buttons defined with `craft\commerce\elements\Order::EVENT_DEFINE_ADDITIONAL_BUTTONS` weren’t displayed on Edit Order screens. ([#3692](https://github.com/craftcms/commerce/issues/3692))
- Fixed a bug where email errors weren’t displayed on Edit Order screens. ([#3693](https://github.com/craftcms/commerce/issues/3693))
- Fixed a bug where `craft\commerce\helpers\Currency::formatAsCurrency()` wasn’t stripping zeros. ([#3716](https://github.com/craftcms/commerce/issues/3716))

## 5.1.3 - 2024-10-02

- Fixed a bug where variants weren’t respecting their product’s propagation method.
- Fixed a PHP error that could occur when creating a new product.
- Fixed a bug where Edit Product screens were showing shipping categories that weren’t available to the current store. ([#3690](https://github.com/craftcms/commerce/issues/3690))
- Fixed a bug where Edit Product screens were showing tax categories that weren’t available to the product type.. ([#3690](https://github.com/craftcms/commerce/issues/3690))
- Fixed a bug where Edit Order screens were displaying the store name twice.
- Fixed a bug where `craft\commerce\models\CatalogPricingRule::$description` was not being populated. ([#3699](https://github.com/craftcms/commerce/issues/3699))
- Fixed a bug where catalog pricing rules were generating prices incorrectly. ([#3700](https://github.com/craftcms/commerce/issues/3700))
- Fixed a PHP error that could occur when deleting a user with orders. ([#3686](https://github.com/craftcms/commerce/issues/3686))

## 5.1.2 - 2024-09-19

- Fixed a bug where shipping methods weren’t validating if a shipping method in a different store had the same name. ([#3676](https://github.com/craftcms/commerce/issues/3676))
- Fixed a bug where any modifications to `craft\commerce\events\CreateSubscriptionEvent::$parameters` weren’t being passed to the gateway’s `subscribe()` method.
- Fixed a bug where stores’ aggregate stock levels weren’t getting updated when inventory changed. ([#3668](https://github.com/craftcms/commerce/issues/3668))
- Fixed a bug where addresses weren’t being automatically added on Edit Order screens. ([#3673](https://github.com/craftcms/commerce/issues/3673))
- Fixed a PHP error that could occur when viewing an Edit Order screen after deleting a purchasable. ([#3677](https://github.com/craftcms/commerce/issues/3677))
- Fixed a bug where some strings weren’t getting translated on Edit Order screens.
- Fixed a JavaScript error that could occur when editing an order.

## 5.1.1 - 2024-09-10

- Fixed XSS vulnerabilities.

## 5.1.0.1 - 2024-09-05

- Fixed a bug where catalog pricing rules weren’t respecting product conditions. ([#3544](https://github.com/craftcms/commerce/issues/3544))

## 5.1.0 - 2024-09-04

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

## 5.0.19 - 2024-09-04

- Fixed a bug where calculated catalog prices weren’t getting rounded to the decimal precision of the store’s currency.
- Fixed a PHP error that occurred when calling `craft\commerce\elements\Variant::getSales()`.
- Fixed a SQL error that could occur when upgrading to Commerce 5.

## 5.0.18 - 2024-08-28

- Fixed a PHP error that could occur when default addresses were set on a cart. ([#3641](https://github.com/craftcms/commerce/issues/3641))
- Fixed a bug were the “Auto Set New Cart Addresses” store setting was not persisting when saved.

## 5.0.17 - 2024-08-21

- Fixed a bug where variant indexes weren’t displaying promotion prices as currency values.
- Fixed a PHP error that could occur when sending an order email. ([#3596](https://github.com/craftcms/commerce/issues/3596))
- Fixed a bug where dimension fields were not displaying values in the correct formatting locale. ([#3636](https://github.com/craftcms/commerce/issues/3636))
- Fixed a bug where users couldn’t access catalog pricing rules when the current user had permission. ([#3639](https://github.com/craftcms/commerce/issues/3639))
- Fixed a bug where available shipping methods were not returned in order of price. ([#3631](https://github.com/craftcms/commerce/issues/3631))

## 5.0.16.2 - 2024-08-16

- Fixed a bug where variants’ `sku` values could be cleared out when saving a product revision.

## 5.0.16.1 - 2024-08-16

- Fixed a bug where variants’ `sku` values could be cleared out when saving a product.
- Fixed a bug where `craft\commerce\elements\Product::getVariants()` wasn’t respecting variants’ site statuses.

## 5.0.16 - 2024-08-14

- It’s now possible to duplicate variants.
- It’s now possible to search for orders by shipping and billing address. ([#3603](https://github.com/craftcms/commerce/pull/3603))
- Fixed a bug where it wasn’t possible to remove the last email from an order status configuration. ([#3621](https://github.com/craftcms/commerce/issues/3621))
- Fixed a bug where the “Create Sale” and “Create Discount” product index actions weren’t working. ([#3611](https://github.com/craftcms/commerce/issues/3611))
- Fixed a bug where `craft\commerce\elements\Order::getOrderStatus()` could incorrectly return `null`. ([#3615](https://github.com/craftcms/commerce/issues/3615))
- Fixed a bug where draft variants became orphaned when products were deleted.
- Fixed a PHP error that occurred when using a custom queue driver. ([#3619](https://github.com/craftcms/commerce/pull/3619))
- Fixed a bug where stat widgets weren’t respecting the user’s preferred week start day. ([#3620](https://github.com/craftcms/commerce/pull/3620))
- Fixed a bug where variants weren’t getting duplicated when duplicating a product. ([#924](https://github.com/craftcms/commerce/issues/924))

## 5.0.15 - 2024-07-31

- Fixed a SQL error that could occur when upgrading to Commerce 5 on PostgreSQL. ([#3600](https://github.com/craftcms/commerce/pull/3600), [#3601](https://github.com/craftcms/commerce/pull/3601))
- Fixed a bug where payment modals weren’t calculating additional payment currencies on Edit Order pages.
- Fixed a PHP error that occurred when retrieving an order that referenced a deleted payment currency.
- Fixed a bug where Edit Variant screens were showing shipping categories that were unrelated to the current store. ([#3608](https://github.com/craftcms/commerce/issues/3608))

## 5.0.14 - 2024-07-24

- Fixed a bug where account activation emails weren’t being sent on order completion. ([#3226](https://github.com/craftcms/commerce/issues/3226))
- Fixed a bug where email previewing wasn’t working on installs with multiple stores. ([#3595](https://github.com/craftcms/commerce/issues/3595))
- Fixed a bug where emails sent via the control panel could be rendered with the wrong language.
- Fixed a SQL error that occurred when exporting order line items. ([#3592](https://github.com/craftcms/commerce/issues/3592))
- Fixed a PHP error that could occur when generating catalog prices. ([#3591](https://github.com/craftcms/commerce/issues/3591))

## 5.0.13 - 2024-07-18

- Fixed a SQL error that could occur when updating Commerce on PostgreSQL. ([#3588](https://github.com/craftcms/commerce/pull/3588))
- Fixed a SQL error that could occur when saving a payment currency. ([#3563](https://github.com/craftcms/commerce/issues/3563))
- Fixed a bug where SCA payment sources prevented subscriptions from starting. ([#3590](https://github.com/craftcms/commerce/pull/3590))
- Fixed PHP error that occurred when saving an estimated billing address. ([#3549](https://github.com/craftcms/commerce/pull/3549))
- Fixed a bug where variant indexes were including table columns for all custom fields added to variant field layouts, across all product types. ([#15373](https://github.com/craftcms/cms/issues/15373))
- Fixed a bug where the “Ignore promotional prices” discount setting wasn’t getting saved correctly. ([#3573](https://github.com/craftcms/commerce/issues/3573))
- Fixed a bug where setting a new default variant wouldn’t persist. ([#3565](https://github.com/craftcms/commerce/issues/3565), [#3564](https://github.com/craftcms/commerce/issues/3564), [#3589](https://github.com/craftcms/commerce/issues/3589))

## 5.0.12.2 - 2024-07-12

- Fixed a bug where shipping rule descriptions weren’t being saved. ([#3580](https://github.com/craftcms/commerce/issues/3580))
- Fixed a SQL error that could occur when updating. ([#3581](https://github.com/craftcms/commerce/issues/3581))

## 5.0.12.1 - 2024-07-12

- Fixed a SQL error that occurred when updating.

## 5.0.12 - 2024-07-11

- Variant index tables can now have a “Promotable” column. ([#3571](https://github.com/craftcms/commerce/issues/3571))
- Added `craft\commerce\base\Purchasable::availableShippingCategories()`.
- Added `craft\commerce\base\Purchasable::availableTaxCategories()`.
- Added `craft\commerce\base\Purchasable::shippingCategoryFieldHtml()`.
- Added `craft\commerce\base\Purchasable::taxCategoryFieldHtml()`.
- Added `craft\commerce\elements\Variant::availableShippingCategories()`.
- Added `craft\commerce\elements\Variant::availableTaxCategories()`.
- Added `craft\commerce\events\PdfRenderEvent::$sourcePdf`. ([#3543](https://github.com/craftcms/commerce/issues/3543))
- Fixed a SQL error that occurred when reordering order statuses on PostgreSQL. ([#3554](https://github.com/craftcms/commerce/issues/3554))
- Fixed a SQL error that could occur when saving a payment currency. ([#3563](https://github.com/craftcms/commerce/issues/3563))
- Fixed a bug where it was possible to select shipping and tax categories that weren’t allowed for the product type. ([#3557](https://github.com/craftcms/commerce/issues/3557))
- Fixed a bug where payment currencies, shipping categories, and tax categories weren’t deletable. ([#3548](https://github.com/craftcms/commerce/issues/3548))
- Fixed a bug where variant field layouts could render incorrectly. ([#3570](https://github.com/craftcms/commerce/issues/3570))
- Fixed a bug where address custom fields weren’t visible on Edit Inventory Location pages. ([#3569](https://github.com/craftcms/commerce/issues/3569))
- Fixed a SQL error that could occur when fixing foreign keys.

## 5.0.11.1 - 2024-06-20

- Fixed a PHP error that could occur on app initialization.

## 5.0.11 - 2024-06-18

- Added `craft\commerce\elements\Product::getDefaultPrice()`.
- Added `craft\commerce\elements\Product::setDefaultPrice()`.
- Fixed a bug where `craft\commerce\elements\Product::$defaultPrice` could return an incorrect value.
- Fixed a bug where default variant attributes weren’t being saved on products.
- Fixed a bug where search wasn’t working on user indexes.

## 5.0.10.1 - 2024-06-14

- Fixed a bug where duplicate Store location addresses were being created.
- Fixed a bug where a customers’ primary address selections weren’t being saved. 

## 5.0.10 - 2024-06-13 

- `craft\elements\User::toArray()` now includes `primaryBillingAddressId` and `primaryShippingAddressId` values in response arrays.
- `craft\elements\Address::toArray()` now includes `isPrimaryBilling` and `isPrimaryShipping` values in response arrays for user addresses.
- Fixed a PHP error that could occur when saving a discount. ([#3538](https://github.com/craftcms/commerce/issues/3538))
- Fixed a bug where the “Edit” action could be incorrectly shown when managing inventory locations.

## 5.0.9 - 2024-06-05

- Product Title fields are no longer shown when “Show the Title field” is disabled and there’s a validation error on the `title` attribute. ([craftcms/cms#13876](https://github.com/craftcms/cms/issues/13876))
- Fixed a PHP error that occurred when saving donation settings on multi-store installs. ([#3521](https://github.com/craftcms/commerce/issues/3521))

## 5.0.8 - 2024-05-29

- Fixed a bug where orders’ `shippingMethodName` values could be cleared out when saving a completed order with a plugin-provided shipping method. ([#3519](https://github.com/craftcms/commerce/issues/3519))
- Fixed a PHP error that could occur on app initialization.
- Fixed missing validation for Inventory Location handles. ([#3511](https://github.com/craftcms/commerce/issues/3511))
- Fixed a SQL error that could occur when switching sites with a cart cookie set. ([#3522](https://github.com/craftcms/commerce/issues/3522))
- Fixed an error that could occur when attempting to save a variant with special characters. ([#3516](https://github.com/craftcms/commerce/issues/3516))

## 5.0.7 - 2024-05-22

- Improved store query performance. ([#3481](https://github.com/craftcms/commerce/issues/3481))
- Added `craft\commerce\gql\types\input\IntFalse`.
- Fixed a bug where disclosure menus on the Stores index page weren’t listing all their items.
- Fixed an SQL error that occurred when querying for purchasables with the `hasStock` param. ([#3505](https://github.com/craftcms/commerce/issues/3505))
- Fixed an error that could occur when querying for products or variants via GraphQL.
- Fixed a SQL error that could occur when generating the pricing catalog. ([#3513](https://github.com/craftcms/commerce/issues/3513))
- Fixed a bug where untracked stock items weren’t displaying correctly in the example templates. ([#3510](https://github.com/craftcms/commerce/issues/3510))
- Fixed a bug where the pricing catalog wasn’t getting updated after a pricing rule was disabled. ([#3515](https://github.com/craftcms/commerce/issues/3515))
- Fixed an SQL error that could occur when switching stores. ([#3501](https://github.com/craftcms/commerce/issues/3501))

## 5.0.6 - 2024-05-15

- Fixed an error that occurred when deleting or duplicating a shipping rule on the Edit Shipping Rule screen. ([#3490](https://github.com/craftcms/commerce/issues/3490))
- Fixed a bug where dimension fields did not respect their product type visibility settings. ([#3493](https://github.com/craftcms/commerce/issues/3493))
- Fixed a SQL error that occurred when updating. ([#3495](https://github.com/craftcms/commerce/pull/3495),[#3496](https://github.com/craftcms/commerce/issues/3496))

## 5.0.5 - 2024-05-09

- Fixed a SQL error that could occur during installation. ([#3492](https://github.com/craftcms/commerce/issues/3492), [#3488](https://github.com/craftcms/commerce/issues/3488))

## 5.0.4 - 2024-05-08

- Fixed a SQL error that could occur on the Edit Store screen. ([#3482](https://github.com/craftcms/commerce/issues/3482))
- Fixed a SQL error that could that occurred when using the `hasSales` variant query param. ([#3483](https://github.com/craftcms/commerce/issues/3483))
- Fixed SQL errors that could occur during installation. ([#3486](https://github.com/craftcms/commerce/issues/3486), [#3488](https://github.com/craftcms/commerce/issues/3488))

## 5.0.3 - 2024-05-02

- Added `craft\commerce\helpers\ProjectConfigData::ensureAllStoresProcessed()`.
- Added `craft\commerce\models\OrderStatus::getConfig()`.
- Fixed a bug where it wasn’t possible to download PDFs from the Orders index page. ([#3477](https://github.com/craftcms/commerce/issues/3477))
- Fixed an error that could occur when installing Craft CMS + Craft Commerce with an existing project config. ([#3472](https://github.com/craftcms/commerce/issues/3472))
- Fixed a bug where order status configs were missing their store assignments after rebuilding the project config. 

## 5.0.2 - 2024-05-01

- Fixed a bug where setting a default tax zone would unset the default zone for all other stores. ([#3473](https://github.com/craftcms/commerce/issues/3473))
- Fixed a bug where email queue jobs weren’t completing. ([#3476](https://github.com/craftcms/commerce/issues/3476))
- Fixed a bug where it wasn’t possible to create a new order for a non-primary store from the control panel. ([#3474](https://github.com/craftcms/commerce/issues/3474))

## 5.0.1 - 2024-05-01

- Fixed a bug where the “Commerce” Edit User screen wasn’t showing.
- Added `craft\commerce\controllers\UsersController`.
- Deprecated `craft\commerce\fields\UserCommerceField`.

## 5.0.0 - 2024-04-30

### Store Management
- It’s now possible to manage multiple stores (up to five). ([#2283](https://github.com/craftcms/commerce/discussions/2283))
- It’s now possible to manage multiple inventory locations (up to five). ([#2286](https://github.com/craftcms/commerce/discussions/2286), [#2669](https://github.com/craftcms/commerce/discussions/2669))
- Added support for catalog pricing of purchasables, improving scalability and pricing flexibility for high-volume stores.
- Products now support drafts, autosaving, and versioning. ([#2358](https://github.com/craftcms/commerce/discussions/2358))
- Product variants are now managed via nested element indexes rather than inline-editable blocks.
- Product variants’ field layouts now support multiple tabs.
- Product pages’ breadcrumbs now include a menu that links to each editable product type.
- It’s now possible to create new products from product select modals when a custom source is selected, if the source is configured to only show products of one type.
- The Products index page now shows a primary “New product” button when a custom source is selected, if the source is configured to only show products of one type.
- Order conditions can now have a “Total Weight” rule.
- Shipping methods and shipping rules now support flexible order matching, based on an order condition.
- Users’ orders, carts, and subscriptions are now managed on a dedicated “Commerce” screen within Edit User sections.

### Administration
- Added a new “Manage inventory stock levels” permission.
- Added a new “Manage inventory locations” permission.

### Development
- Added the `currentStore` Twig variable.
- Added `commerce/pricing-catalog/generate` command.
- Deprecated the `hasUnlimitedStock` variant query param. `inventoryTracked` should be used instead.
- Removed the `shippingCategory`, `shippingCategoryId`, `taxCategory`, and `taxCategoryId` product query params. The corresponding variant query params can be used instead.
- Removed the `showEditUserCommerceTab` config setting.

### Extensibility
- Added `craft\commerce\base\CatalogPricingConditionRuleInterface`.
- Added `craft\commerce\base\EnumHelpersTrait`
- Added `craft\commerce\base\HasStoreInterface`.
- Added `craft\commerce\base\InventoryMovementInterface`.
- Added `craft\commerce\base\InventoryMovement`.
- Added `craft\commerce\base\Purchasable::$availableForPurchase`.
- Added `craft\commerce\base\Purchasable::$freeShipping`.
- Added `craft\commerce\base\Purchasable::$height`.
- Added `craft\commerce\base\Purchasable::$inventoryTracked`
- Added `craft\commerce\base\Purchasable::$length`.
- Added `craft\commerce\base\Purchasable::$maxQty`.
- Added `craft\commerce\base\Purchasable::$minQty`.
- Added `craft\commerce\base\Purchasable::$promotable`.
- Added `craft\commerce\base\Purchasable::$shippingCategoryId`.
- Added `craft\commerce\base\Purchasable::$stock`
- Added `craft\commerce\base\Purchasable::$taxCategoryId`.
- Added `craft\commerce\base\Purchasable::$weight`.
- Added `craft\commerce\base\Purchasable::$width`.
- Added `craft\commerce\base\Purchasable::getInventoryItem()`.
- Added `craft\commerce\base\Purchasable::getInventoryLevels()`.
- Added `craft\commerce\base\Purchasable::getOnPromotion()`.
- Added `craft\commerce\base\Purchasable::getPrice()`.
- Added `craft\commerce\base\Purchasable::getPromotionalPrice()`.
- Added `craft\commerce\base\Purchasable::getStock()`
- Added `craft\commerce\base\Purchasable::getStore()`
- Added `craft\commerce\base\Purchasable::setPrice()`.
- Added `craft\commerce\base\Purchasable::setPromotionalPrice()`.
- Added `craft\commerce\base\StoreRecordTrait`.
- Added `craft\commerce\base\StoreTrait`.
- Added `craft\commerce\behaviors\StoreBehavior`.
- Added `craft\commerce\collections\InventoryMovementCollection`
- Added `craft\commerce\collections\UpdateInventoryLevelCollection`
- Added `craft\commerce\console\controllers\CatalogPricingController`.
- Added `craft\commerce\controllers\CatalogPricingController`.
- Added `craft\commerce\controllers\CatalogPricingRulesController`.
- Added `craft\commerce\controllers\InventoryLocationsController`
- Added `craft\commerce\controllers\InventoryLocationsStoresController`
- Added `craft\commerce\controllers\VariantsController`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_RULES_USERS`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_RULES`.
- Added `craft\commerce\db\Table::CATALOG_PRICING`.
- Added `craft\commerce\db\Table::INVENTORYITEMS`.
- Added `craft\commerce\db\Table::INVENTORYLOCATIONS_STORES`.
- Added `craft\commerce\db\Table::INVENTORYLOCATIONS`.
- Added `craft\commerce\db\Table::INVENTORYMOVEMENTS`.
- Added `craft\commerce\db\Table::PURCHASABLES_STORES`.
- Added `craft\commerce\db\Table::STORESETTINGS`.
- Added `craft\commerce\db\Table::STORES`.
- Added `craft\commerce\db\Table::TRANSFERS_INVENTORYITEMS`.
- Added `craft\commerce\db\Table::TRANSFERS`.
- Added `craft\commerce\elements\Product::getVariantManager()`.
- Added `craft\commerce\elements\Variant::getProductSlug()`.
- Added `craft\commerce\elements\Variant::getProductTypeHandle()`.
- Added `craft\commerce\elements\Variant::setProductSlug()`.
- Added `craft\commerce\elements\Variant::setProductTypeHandle()`.
- Added `craft\commerce\elements\VariantCollection`.
- Added `craft\commerce\elements\actions\SetDefaultVariant`.
- Added `craft\commerce\elements\conditions\customer\CatalogPricingCustomerCondition`.
- Added `craft\commerce\elements\conditions\orders\DiscountedItemSubtotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ShippingAddressZoneConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition`.
- Added `craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition`.
- Added `craft\commerce\elements\conditions\orders\TotalWeightConditionRule`.
- Added `craft\commerce\elements\conditions\products\ProductVariantInventoryTrackedConditionRule`.
- Added `craft\commerce\elements\conditions\purchasables\CatalogPricingCondition`.
- Added `craft\commerce\elements\conditions\purchasables\CatalogPricingCustomerConditionRule`.
- Added `craft\commerce\elements\conditions\purchasables\CatalogPricingPurchasableConditionRule`.
- Added `craft\commerce\elements\conditions\purchasables\PurchasableConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$totalWeight`.
- Added `craft\commerce\elements\db\OrderQuery::totalWeight()`.
- Added `craft\commerce\elements\traits\OrderValidatorsTrait::validateOrganizationTaxIdAsVatId()`.
- Added `craft\commerce\enums\InventoryTransactionType`.
- Added `craft\commerce\enums\InventoryUpdateQuantityType`.
- Added `craft\commerce\events\RegisterAvailableShippingMethodsEvent::getShippingMethods()`.
- Added `craft\commerce\events\RegisterAvailableShippingMethodsEvent::setShippingMethods()`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleAllowedQtyField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleAvailableForPurchaseField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleDimensionsField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleFreeShippingField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabalePriceField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabalePromotableField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleSkuField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleStockField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleWeightField`.
- Added `craft\commerce\helpers\Cp`.
- Added `craft\commerce\helpers\Currency::moneyInputHtml()`.
- Added `craft\commerce\helpers\Purchasable::catalogPricingRulesTableByPurchasableId()`.
- Added `craft\commerce\models\CatalogPricingRule`.
- Added `craft\commerce\models\Discount::$storeId`.
- Added `craft\commerce\models\InventoryItem`.
- Added `craft\commerce\models\InventoryLocation`.
- Added `craft\commerce\models\InventoryTransaction`.
- Added `craft\commerce\models\Level`.
- Added `craft\commerce\models\LineItem::getSnapshot()`.
- Added `craft\commerce\models\LineItem::setSnapshot()`.
- Added `craft\commerce\models\LineItems::getFulfilledTotalQuantity()`.
- Added `craft\commerce\models\PaymentSources::getStore()`.
- Added `craft\commerce\models\ProductType::$maxVariants`.
- Added `craft\commerce\models\PurchasableStore`.
- Added `craft\commerce\models\Store::getInventoryLocations()`.
- Added `craft\commerce\models\Store::getInventoryLocationsOptions()`.
- Added `craft\commerce\models\inventory\InventoryCommittedMovement`
- Added `craft\commerce\models\inventory\InventoryLocationDeactivatedMovement`.
- Added `craft\commerce\models\inventory\InventoryManualMovement`.
- Added `craft\commerce\models\inventory\UpdateInventoryLevel`.
- Added `craft\commerce\plugin\Services::getVat()`.
- Added `craft\commerce\records\CatalogPricingRulePurchasable`.
- Added `craft\commerce\records\CatalogPricingRuleUser`.
- Added `craft\commerce\records\CatalogPricingRule`.
- Added `craft\commerce\records\CatalogPricing`.
- Added `craft\commerce\records\InventoryItem`.
- Added `craft\commerce\records\InventoryLocation`.
- Added `craft\commerce\records\PurchasableStore`.
- Added `craft\commerce\services\CatalogPricingRules`.
- Added `craft\commerce\services\CatalogPricing`.
- Added `craft\commerce\services\Discounts::getAllDiscountsByStoreId()`.
- Added `craft\commerce\services\InventoryLocations`.
- Added `craft\commerce\services\Inventory`.
- Added `craft\commerce\services\OrderStatuses::getOrderStatusByUid()`.
- Added `craft\commerce\services\Purchasables::updateStoreStockCache()`
- Added `craft\commerce\services\Sales::canUseSales()`.
- Added `craft\commerce\services\ShippingCategories::clearCaches()`.
- Added `craft\commerce\services\Stores`.
- Added `craft\commerce\services\Vat`.
- Added `craft\commerce\web\assets\inventory\InventoryAsset`.
- Deprecated `craft\commerce\base\Purchasable::getOnSale()`. `getOnPromotion()` should be used instead.
- Deprecated `craft\commerce\base\Variant::hasUnlimitedStock()`. `craft\commerce\base\Purchasable::$inventoryTracked` should be used instead.
- Deprecated `craft\commerce\elements\Order::$totalSaleAmount`. `$totalPromotionalAmount` should be used instead.
- Deprecated `craft\commerce\elements\Variant::getProduct()`. `getOwner()` should be used instead.
- Deprecated `craft\commerce\elements\Variant::getProductId()`. `getOwnerId()` should be used instead.
- Deprecated `craft\commerce\elements\Variant::setProduct()`. `setOwner()` should be used instead.
- Deprecated `craft\commerce\elements\Variant::setProductId()`. `setOwnerId()` should be used instead.
- Deprecated `craft\commerce\elements\conditions\products\ProductVariantHasUnlimitedStockConditionRule`. `ProductVariantInventoryTrackedConditionRule` should be used instead.
- Deprecated `craft\commerce\models\Store::getCountries()`. `craft\commerce\models\Store::getSettings()->getCountries()` should be used instead.
- Deprecated `craft\commerce\models\Store::getMarketAddressCondition()`. `craft\commerce\models\Store::getSettings()->getMarketAddressCondition()` should be used instead.
- Deprecated `craft\commerce\models\Store::setCountries()`. `craft\commerce\models\Store::getSettings()->setCountries()` should be used instead.
- Removed `craft\commerce\base\PurchasableInterface::getId()`.
- Removed `craft\commerce\base\Variant::$unlimitedStock`. `craft\commerce\base\Purchasable::$inventoryTracked` can be used instead.
- Removed `craft\commerce\console\controllers\UpgradeController`.
- Removed `craft\commerce\controllers\LiteShippingController`.
- Removed `craft\commerce\controllers\LiteTaxController`.
- Removed `craft\commerce\controllers\ProductsController::actionDeleteProduct()`.
- Removed `craft\commerce\controllers\ProductsController::actionDuplicateProduct()`.
- Removed `craft\commerce\controllers\ProductsController::actionVariantIndex()`.
- Removed `craft\commerce\controllers\ProductsPreviewController`.
- Removed `craft\commerce\elements\Product::$availableForPurchase`. `craft\commerce\base\Purchasable::$availableForPurchase` can be used instead.
- Removed `craft\commerce\elements\Product::$promotable`. `craft\commerce\base\Purchasable::$promotable` can be used instead.
- Removed `craft\commerce\elements\Product::$shippingCategoryId`. `craft\commerce\base\Purchasable::$shippingCategoryId` can be used instead.
- Removed `craft\commerce\elements\Product::$taxCategoryId`. `craft\commerce\base\Purchasable::$taxCategoryId` can be used instead.
- Removed `craft\commerce\elements\Variant::$stock`. `craft\commerce\base\Purchasable::getStock()` can be used instead.
- Removed `craft\commerce\helpers\Product`.
- Removed `craft\commerce\helpers\VariantMatrix`.
- Removed `craft\commerce\helpers\VariantMatrix`.
- Removed `craft\commerce\models\Currency`.
- Removed `craft\commerce\models\Discount::$baseDiscountType`.
- Removed `craft\commerce\models\LiteShippingSettings`.
- Removed `craft\commerce\models\LiteTaxSettings`.
- Removed `craft\commerce\models\ProductType::$hasVariants`. `$maxVariants` can be used instead.
- Removed `craft\commerce\models\Settings::$allowCheckoutWithoutPayment`. `craft\commerce\models\Store::getAllowCheckoutWithoutPayment()` can be used instead.
- Removed `craft\commerce\models\Settings::$allowEmptyCartOnCheckout`. `craft\commerce\models\Store::getAllowEmptyCartOnCheckout()` can be used instead.
- Removed `craft\commerce\models\Settings::$allowPartialPaymentOnCheckout`. `craft\commerce\models\Store::getAllowPartialPaymentOnCheckout()` can be used instead.
- Removed `craft\commerce\models\Settings::$autoSetCartShippingMethodOption`. `craft\commerce\models\Store::getAutoSetCartShippingMethodOption()` can be used instead.
- Removed `craft\commerce\models\Settings::$autoSetNewCartAddresses`. `craft\commerce\models\Store::getAutoSetNewCartAddresses()` can be used instead.
- Removed `craft\commerce\models\Settings::$autoSetPaymentSource`. `craft\commerce\models\Store::getAutoSetPaymentSource()` can be used instead.
- Removed `craft\commerce\models\Settings::$emailSenderAddressPlaceholder`.
- Removed `craft\commerce\models\Settings::$emailSenderAddress`. `craft\commerce\models\Email::$senderAddress` can be used instead.
- Removed `craft\commerce\models\Settings::$emailSenderNamePlaceholder`.
- Removed `craft\commerce\models\Settings::$emailSenderName`. `craft\commerce\models\Email::$senderName` can be used instead.
- Removed `craft\commerce\models\Settings::$freeOrderPaymentStrategy`. `craft\commerce\models\Store::getFreeOrderPaymentStrategy()` can be used instead.
- Removed `craft\commerce\models\Settings::$minimumTotalPriceStrategy`. `craft\commerce\models\Store::getMinimumTotalPriceStrategy()` can be used instead.
- Removed `craft\commerce\models\Settings::$pdfPaperOrientation`. `craft\commerce\models\Pdf::$paperOrientation` can be used instead.
- Removed `craft\commerce\models\Settings::$pdfPaperSize`. `craft\commerce\models\Pdf::$paperSize` can be used instead.
- Removed `craft\commerce\models\Settings::$requireBillingAddressAtCheckout`. `craft\commerce\models\Store::getRequireBillingAddressAtCheckout()` can be used instead.
- Removed `craft\commerce\models\Settings::$requireShippingAddressAtCheckout`. `craft\commerce\models\Store::getRequireShippingAddressAtCheckout()` can be used instead.
- Removed `craft\commerce\models\Settings::$requireShippingMethodSelectionAtCheckout`. `craft\commerce\models\Store::getRequireShippingMethodSelectionAtCheckout()` can be used instead.
- Removed `craft\commerce\models\Settings::$useBillingAddressForTax`. `craft\commerce\models\Store::getUseBillingAddressForTax()` can be used instead.
- Removed `craft\commerce\models\Settings::$validateBusinessTaxIdasVatId`. `craft\commerce\models\Store::getValidateOrganizationTaxIdasVatId()` can be used instead.
- Removed `craft\commerce\models\Settings::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE`. `craft\commerce\models\Store::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE` can be used instead.
- Removed `craft\commerce\models\Settings::FREE_ORDER_PAYMENT_STRATEGY_PROCESS`. `craft\commerce\models\Store::FREE_ORDER_PAYMENT_STRATEGY_PROCESS` can be used instead.
- Removed `craft\commerce\models\Settings::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT`. `craft\commerce\models\Store::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT` can be used instead.
- Removed `craft\commerce\models\Settings::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING`. `craft\commerce\models\Store::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING` can be used instead.
- Removed `craft\commerce\models\Settings::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO`. `craft\commerce\models\Store::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO` can be used instead.
- Removed `craft\commerce\models\ShippingRule::$maxQty`.
- Removed `craft\commerce\models\ShippingRule::$maxTotal`.
- Removed `craft\commerce\models\ShippingRule::$maxWeight`.
- Removed `craft\commerce\models\ShippingRule::$minMaxTotalType`.
- Removed `craft\commerce\models\ShippingRule::$minQty`.
- Removed `craft\commerce\models\ShippingRule::$minTotal`.
- Removed `craft\commerce\models\ShippingRule::$minWeight`.
- Removed `craft\commerce\models\ShippingRule::$shippingZoneId`.
- Removed `craft\commerce\models\ShippingRule::getShippingZone()`.
- Removed `craft\commerce\records\Discount::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED`.
- Removed `craft\commerce\records\Discount::BASE_DISCOUNT_TYPE_PERCENT_ITEMS`.
- Removed `craft\commerce\records\Discount::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED`.
- Removed `craft\commerce\records\Discount::BASE_DISCOUNT_TYPE_PERCENT_TOTAL`.
- Removed `craft\commerce\records\Discount::BASE_DISCOUNT_TYPE_VALUE`.
- Removed `craft\commerce\records\ShippingRule::TYPE_MIN_MAX_TOTAL_SALEPRICE_WITH_DISCOUNTS`.
- Removed `craft\commerce\records\ShippingRule::TYPE_MIN_MAX_TOTAL_SALEPRICE`.
- Removed `craft\commerce\records\ShippingRule::getShippingZone()`.
- Removed `craft\commerce\services\Customers::addEditUserCommerceTab()`.
- Removed `craft\commerce\services\Customers::addEditUserCommerceTabContent()`.
- Removed `craft\commerce\services\PaymentSources::getAllGatewayPaymentSourcesByUserId()`.
- Removed `craft\commerce\services\PaymentSources::getAllPaymentSourcesByUserId()`.
- Removed `craft\commerce\services\TaxRates::getTaxRatesForZone()`.
- Removed `craft\commerce\validators\StoreCountryValidator`.
- Removed `craft\commerce\widgets\Orders::$orderStatusId`. `$orderStatuses` can be used instead.
- `craft\commerce\base\PurchasableInterface` now extends `craft\base\ElementInterface`.
- `craft\commerce\elements\Product::getVariants()` now returns a collection.
- `craft\commerce\elements\Variant` now implements `craft\base\NestedElementTrait`.
- `craft\commerce\elements\db\PurchasableQuery` is now abstract.
- `craft\commerce\services\Discounts::getAllDiscounts()` now returns a collection.
- `craft\commerce\services\Gateways::getAllCustomerEnabledGateways()` now returns a collection.
- `craft\commerce\services\Gateways::getAllGateways()` now returns a collection.
- `craft\commerce\services\PaymentSources::getAllGatewayPaymentSourcesByCustomerId()` now returns a collection.
- `craft\commerce\services\PaymentSources::getAllPaymentSourcesByCustomerId()` now returns a collection.
- `craft\commerce\services\PaymentSources::getAllPaymentSourcesByGatewayId()` now returns a collection.
- `craft\commerce\services\ShippingCategories::getAllShippingCategories()` now returns a collection.
- `craft\commerce\services\ShippingMethods::getAllShippingMethods()` now returns a collection.
- `craft\commerce\services\ShippingRules::getAllShippingRules()` now returns a collection.
- `craft\commerce\services\ShippingRules::getAllShippingRulesByShippingMethodId()` now returns a collection.
- `craft\commerce\services\TaxRates::getAllTaxRates()` now returns a collection.
- `craft\commerce\services\TaxRates::getTaxRatesByTaxZoneId()` now returns a collection.
- `craft\commerce\services\TaxZones::getAllTaxZones()` now returns a collection.
- Renamed `craft\commerce\base\Purchasable::tableAttributeHtml()` to `attributeHtml()`.
- Renamed `craft\commerce\controllers\BaseStoreSettingsController` to `BaseStoreManagementController`.
- Renamed `craft\commerce\controllers\StoreSettingsController` to `StoreManagementController`.
- Renamed `craft\commerce\elements\Subscription::tableAttributeHtml()` to `attributeHtml()`.
- Renamed `craft\commerce\elements\Variant::tableAttributeHtml()` to `attributeHtml()`.
- Renamed `craft\commerce\elements\traits\OrderElementTrait::tableAttributeHtml()` to `attributeHtml()`.

### System
- Craft Commerce now requires Craft CMS 5.1 or later.
- Craft Commerce now strictly requires Craft CMS Pro edition.
