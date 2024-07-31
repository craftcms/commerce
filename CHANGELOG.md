# Release Notes for Craft Commerce

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
