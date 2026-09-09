<?php

namespace craft\commerce\services;

use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)` instead.
 */
class CatalogPricingRules extends Component
{
    public function hasCatalogPricingRules(): bool
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->hasCatalogPricingRules();
    }

    public function canUseCatalogPricingRules(): bool
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->canUseCatalogPricingRules();
    }

    public function getCatalogPricingRuleById(int $id, ?int $storeId = null): ?CatalogPricingRule
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getCatalogPricingRuleById($id, $storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllEnabledCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllEnabledCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllActiveCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllActiveCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesWithUserConditions(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllCatalogPricingRulesWithUserConditions($storeId);
    }

    public function generateRulePriceFromPrice(?float $basePrice, ?float $basePromotionalPrice, CatalogPricingRule $catalogPricingRule): ?float
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $catalogPricingRule);
    }

    public function saveCatalogPricingRule(CatalogPricingRule $catalogPricingRule, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule, $runValidation);
    }

    public function deleteCatalogPricingRuleById(int $id): bool
    {
        return app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->deleteCatalogPricingRuleById($id);
    }
}
