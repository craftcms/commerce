<?php

namespace craft\commerce\services;

use craft\commerce\records\CatalogPricingQueue as CatalogPricingQueueRecord;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use craft\events\ModelEvent;
use craft\queue\QueueInterface;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\queue\Queue;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\CatalogPricing::class)` instead.
 */
class CatalogPricing extends Component
{
    public function generateCatalogPrices(?array $purchasableIds = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false, Queue|QueueInterface|null $queue = null): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->generateCatalogPrices($purchasableIds, $catalogPricingRules, $showConsoleOutput, $queue);
    }

    public function getCatalogPrice(int $purchasableId, ?int $storeId = null, ?int $userId = null, bool $isPromotionalPrice = false): ?float
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->getCatalogPrice($purchasableId, $storeId, $userId, $isPromotionalPrice);
    }

    public function getCatalogPricesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->getCatalogPricesByPurchasableId($purchasableId, $storeId);
    }

    public function getCatalogPrices(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, ?int $limit = null, ?int $offset = null): Collection
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->getCatalogPrices($storeId, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);
    }

    public function getCatalogPricesPageInfo(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, int $limit = 100, int $offset = 0): mixed
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->getCatalogPricesPageInfo($storeId, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);
    }

    public function markPricesAsUpdatePending(int|array|null $catalogPricingRuleId = null, int|array|null $purchasableId = null, int|array|null $storeId = null): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->markPricesAsUpdatePending($catalogPricingRuleId, $purchasableId, $storeId);
    }

    public function afterSavePurchasableHandler(ModelEvent $event): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->afterSavePurchasableHandler($event);
    }

    public function createCatalogPricingJob(array $config = [], int $priority = 100): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->createCatalogPricingJob($config, $priority);
    }

    public function areCatalogPricingJobsRunning(): bool
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->areCatalogPricingJobsRunning();
    }

    public function reserveCatalogPricingQueueRow(): ?CatalogPricingQueueRecord
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->reserveCatalogPricingQueueRow();
    }

    public function releaseCatalogPricingQueueRowById(int $id): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->releaseCatalogPricingQueueRowById($id);
    }

    public function deleteCatalogPricingQueueRowById(int $id): void
    {
        app(\CraftCms\Commerce\Services\CatalogPricing::class)->deleteCatalogPricingQueueRowById($id);
    }

    // TODO: return type will differ (Builder vs craft\db\Query) — update callers when migrated
    public function createCatalogPricingQuery(?int $userId = null, int|string|null $storeId = null, ?bool $isPromotionalPrice = null, bool $allPrices = false, ?CatalogPricingCondition $condition = null): mixed
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->createCatalogPricingQuery($userId, $storeId, $isPromotionalPrice, $allPrices, $condition);
    }

    // TODO: return type will differ (Builder vs craft\db\Query) — update callers when migrated
    public function createCatalogPricesQuery(?int $userId = null, int|string|null $storeId = null, bool $allPrices = false, ?CatalogPricingCondition $condition = null): mixed
    {
        return app(\CraftCms\Commerce\Services\CatalogPricing::class)->createCatalogPricesQuery($userId, $storeId, $allPrices, $condition);
    }
}
