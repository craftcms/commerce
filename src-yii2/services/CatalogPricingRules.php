<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Catalog\Models\CatalogPricingRule;
use craft\events\ModelEvent;
use craft\events\UserGroupsAssignEvent;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\CatalogPricingRules::class)` instead.
 */
class CatalogPricingRules extends Component
{
    public function hasCatalogPricingRules(): bool
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->hasCatalogPricingRules();
    }

    public function canUseCatalogPricingRules(): bool
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->canUseCatalogPricingRules();
    }

    public function getCatalogPricingRuleById(int $id, ?int $storeId = null): ?CatalogPricingRule
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getCatalogPricingRuleById($id, $storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getAllCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllEnabledCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getAllEnabledCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllActiveCatalogPricingRules(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getAllActiveCatalogPricingRules($storeId);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesWithUserConditions(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->getAllCatalogPricingRulesWithUserConditions($storeId);
    }

    public function generateRulePriceFromPrice(?float $basePrice, ?float $basePromotionalPrice, CatalogPricingRule $catalogPricingRule): ?float
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $catalogPricingRule);
    }

    public function afterSaveUserHandler(ModelEvent|UserGroupsAssignEvent $event): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->afterSaveUserHandler($event);
    }

    public function saveCatalogPricingRule(CatalogPricingRule $catalogPricingRule, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule, $runValidation);
    }

    public function deleteCatalogPricingRuleById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\CatalogPricingRules::class)->deleteCatalogPricingRuleById($id);
    }
}
