# Release Notes for Craft Commerce 5.0 (WIP)

## 5.0.0 - Unreleased

### Store Management

### Accessibility

### Administration

### Development

### Extensibility
- Added `craft\commerce\base\Purchasable::getAvailableForPurchase()`.
- Added `craft\commerce\base\Purchasable::getFreeShipping()`.
- Added `craft\commerce\base\Purchasable::getHasUnlimitedStock()`.
- Added `craft\commerce\base\Purchasable::getHeight()`.
- Added `craft\commerce\base\Purchasable::getLength()`.
- Added `craft\commerce\base\Purchasable::getMaxQty()`.
- Added `craft\commerce\base\Purchasable::getMinQty()`.
- Added `craft\commerce\base\Purchasable::getOnPromotion()`.
- Added `craft\commerce\base\Purchasable::getPrice()`.
- Added `craft\commerce\base\Purchasable::getPromotable()`.
- Added `craft\commerce\base\Purchasable::getPromotionalPrice()`.
- Added `craft\commerce\base\Purchasable::getPurchasableStores()`.
- Added `craft\commerce\base\Purchasable::getPurchasableStoreValue()`.
- Added `craft\commerce\base\Purchasable::getShippingCategoryId()`.
- Added `craft\commerce\base\Purchasable::getStock()`.
- Added `craft\commerce\base\Purchasable::getStore()`.
- Added `craft\commerce\base\Purchasable::getTaxCategoryId()`.
- Added `craft\commerce\base\Purchasable::getWeight()`.
- Added `craft\commerce\base\Purchasable::getWidth()`.
- Added `craft\commerce\base\Purchasable::setAvailableForPurchase()`.
- Added `craft\commerce\base\Purchasable::setFreeShipping()`.
- Added `craft\commerce\base\Purchasable::setHasUnlimitedStock()`.
- Added `craft\commerce\base\Purchasable::setHeight()`.
- Added `craft\commerce\base\Purchasable::setLength()`.
- Added `craft\commerce\base\Purchasable::setMaxQty()`.
- Added `craft\commerce\base\Purchasable::setMinQty()`.
- Added `craft\commerce\base\Purchasable::setPrice()`.
- Added `craft\commerce\base\Purchasable::setPromotable()`.
- Added `craft\commerce\base\Purchasable::setPromotionalPrice()`.
- Added `craft\commerce\base\Purchasable::setPurchasableStores()`.
- Added `craft\commerce\base\Purchasable::setPurchasableStoreValue()`.
- Added `craft\commerce\base\Purchasable::setShippingCategoryId()`.
- Added `craft\commerce\base\Purchasable::setStock()`.
- Added `craft\commerce\base\Purchasable::setTaxCategoryId()`.
- Added `craft\commerce\base\Purchasable::setWeight()`.
- Added `craft\commerce\base\Purchasable::setWidth()`.
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