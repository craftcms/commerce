# Release Notes for Craft Commerce 5.0 (WIP)

## 5.0.0 - Unreleased

### Store Management
- Order conditions can now have a “Total Weight” rule.
- Shipping methods can now have condition builders, enabling flexible matching based on the order.
- Shipping rules can now have condition builders, enabling flexible matching based on the order.

### Accessibility

### Administration

### Development
- `craft\commerce\services\Discounts::getAllDiscounts()` now returns a collection.

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
- Added `craft\commerce\base\StoreTrait`.
- Added `craft\commerce\base\StoreRecordTrait`.
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
- Added `craft\commerce\elements\conditions\orders\DiscountedItemSubtotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ShippingAddressZoneConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition`.
- Added `craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition`.
- Added `craft\commerce\elements\conditions\orders\TotalWeightConditionRule`.
- Added `craft\commerce\elements\conditions\purchasables\CatalogPricingPurchasableCondition`.
- Added `craft\commerce\elements\conditions\purchasables\PurchasableConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$totalWeight`.
- Added `craft\commerce\elements\db\OrderQuery::totalWeight()`.
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
- Added `craft\commerce\services\Discounts::getAllDiscountsByStoreId()`.
- Removed `craft\commerce\models\ProductType::$hasVariants`.
- Removed `craft\commerce\models\ShippingRule::$maxQty`.
- Removed `craft\commerce\models\ShippingRule::$maxTotal`.
- Removed `craft\commerce\models\ShippingRule::$maxWeight`.
- Removed `craft\commerce\models\ShippingRule::$minMaxTotalType`.
- Removed `craft\commerce\models\ShippingRule::$minQty`.
- Removed `craft\commerce\models\ShippingRule::$minTotal`.
- Removed `craft\commerce\models\ShippingRule::$minWeight`.
- Removed `craft\commerce\models\ShippingRule::$shippingZoneId`.
- Removed `craft\commerce\models\ShippingRule::getShippingZone()`.
- Removed `craft\commerce\records\ShippingRule::TYPE_MIN_MAX_TOTAL_SALEPRICE`.
- Removed `craft\commerce\records\ShippingRule::TYPE_MIN_MAX_TOTAL_SALEPRICE_WITH_DISCOUNTS`.
- Removed `craft\commerce\records\ShippingRule::getShippingZone()`.
- Removed `craft\commerce\services\TaxRates::getTaxRatesForZone()`.

### System