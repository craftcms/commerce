<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void generateCatalogPrices(array|null $purchasableIds = null, array|null $catalogPricingRules = null, \Symfony\Component\Console\Output\OutputInterface|null $output = null, mixed $queue = null)
 * @method static float|null getCatalogPrice(int $purchasableId, int|null $storeId = null, int|null $userId = null, bool $isPromotionalPrice = false)
 * @method static \Illuminate\Support\Collection getCatalogPricesByPurchasableId(int $purchasableId, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getCatalogPrices(int $storeId, \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition|null $conditionBuilder = null, bool $includeBasePrices = true, string|null $searchText = null, int|null $limit = null, int|null $offset = null)
 * @method static array getCatalogPricesPageInfo(int $storeId, \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition|null $conditionBuilder = null, bool $includeBasePrices = true, string|null $searchText = null, int $limit = 100, int $offset = 0)
 * @method static void markPricesAsUpdatePending(array|int|null $catalogPricingRuleId = null, array|int|null $purchasableId = null, array|int|null $storeId = null)
 * @method static void createCatalogPricingJob(array $config = [], int $priority = 100)
 * @method static bool areCatalogPricingJobsRunning()
 * @method static \CraftCms\Commerce\CatalogPricing\Models\CatalogPricingQueue|null reserveCatalogPricingQueueRow()
 * @method static void releaseCatalogPricingQueueRowById(int $id)
 * @method static void deleteCatalogPricingQueueRowById(int $id)
 * @method static \Illuminate\Database\Query\Builder createCatalogPricesQuery(int|null $userId = null, string|int|null $storeId = null, bool $allPrices = false, \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition|null $condition = null)
 *
 * @see \CraftCms\Commerce\CatalogPricing\CatalogPricing
 */
class CatalogPricing extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\CatalogPricing\CatalogPricing::class;
    }
}
