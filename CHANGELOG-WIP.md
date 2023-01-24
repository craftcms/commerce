# Release Notes for Craft Commerce 5.0 (WIP)

## 5.0.0 - Unreleased

### Store Management

### Accessibility

### Administration

### Development

### Extensibility
- Added `craft\commerce\base\Purchasable::$availableForPurchase`.
- Added `craft\commerce\base\Purchasable::$freeShipping`.
- Added `craft\commerce\base\Purchasable::$hasUnlimitedStock`.
- Added `craft\commerce\base\Purchasable::$height`.
- Added `craft\commerce\base\Purchasable::$length`.
- Added `craft\commerce\base\Purchasable::$maxQty`.
- Added `craft\commerce\base\Purchasable::$minQty`.
- Added `craft\commerce\base\Purchasable::$promotable`.
- Added `craft\commerce\base\Purchasable::$shippingCategoryId`.
- Added `craft\commerce\base\Purchasable::$stock`.
- Added `craft\commerce\base\Purchasable::$taxCategoryId`.
- Added `craft\commerce\base\Purchasable::$weight`.
- Added `craft\commerce\base\Purchasable::$width`.
- Added `craft\commerce\base\Purchasable::getOnPromotion()`.
- Added `craft\commerce\base\Purchasable::getPrice()`.
- Added `craft\commerce\base\Purchasable::getPromotionalPrice()`.
- Added `craft\commerce\base\Purchasable::getStore()`.
- Added `craft\commerce\base\Purchasable::setPrice()`.
- Added `craft\commerce\base\Purchasable::setPromotionalPrice()`.
- Added `craft\commerce\console\controllers\CatalogPricingController`.
- Added `craft\commerce\controllers\CatalogPricingRulesController`.
- Added `craft\commerce\controllers\VariantsController`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_RULES_USERS`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_RULES`.
- Added `craft\commerce\db\Table::CATALOG_PRICING`.
- Added `craft\commerce\db\Table::PURCHASABLES_STORES`.
- Added `craft\commerce\db\Table::STORES`.
- Added `craft\commerce\db\Table::STORESETTINGS`.
- Added `craft\commerce\elements\conditions\customer\CatalogPricingCustomerCondition`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleAllowedQtyField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleAvailableForPurchaseField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleDimensionsField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleFreeShippingField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabalePriceField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabalePromotableField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleSkuField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleStockField`.
- Added `craft\commerce\fieldlayoutelements\PurchasabaleWeightField`.
- Added `craft\commerce\models\CatalogPricingRule`.
- Added `craft\commerce\models\Discount::$storeId`.
- Added `craft\commerce\models\ProductType::$maxVariants`.
- Added `craft\commerce\models\PurchasableStore`.
- Added `craft\commerce\records\CatalogPricing`.
- Added `craft\commerce\records\CatalogPricingRule`.
- Added `craft\commerce\records\CatalogPricingRulePurchasable`.
- Added `craft\commerce\records\CatalogPricingRuleUser`.
- Added `craft\commerce\records\PurchasableStore`.
- Added `craft\commerce\services\CatalogPricing`.
- Added `craft\commerce\services\CatalogPricingRules`.
- Removed `craft\commerce\models\ProductType::$hasVariants`.

### System