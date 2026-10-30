<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool hasCatalogPricingRules()
 * @method static bool canUseCatalogPricingRules()
 * @method static \CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule|null getCatalogPricingRuleById(int $id, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllCatalogPricingRules(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllCatalogPricingRulesByPurchasableId(int $purchasableId, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllEnabledCatalogPricingRules(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllActiveCatalogPricingRules(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllCatalogPricingRulesWithUserConditions(int|null $storeId = null)
 * @method static float|null generateRulePriceFromPrice(float|null $basePrice, float|null $basePromotionalPrice, \CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule $catalogPricingRule)
 * @method static void afterSaveUserHandler(\CraftCms\Cms\Element\Events\ElementSaved|\CraftCms\Cms\User\Events\UserAssignedToGroups $event)
 * @method static bool saveCatalogPricingRule(\CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule $catalogPricingRule, bool $runValidation = true)
 * @method static bool deleteCatalogPricingRuleById(int $id)
 *
 * @see \CraftCms\Commerce\CatalogPricing\CatalogPricingRules
 */
class CatalogPricingRules extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class;
    }
}
