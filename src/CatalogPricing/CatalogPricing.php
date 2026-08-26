<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing;

use craft\helpers\Console;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Commerce\Catalog\Models\CatalogPricing as CatalogPricingModel;
use CraftCms\Commerce\Catalog\Models\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule;
use CraftCms\Commerce\CatalogPricing\Jobs\CatalogPricingJob;
use CraftCms\Commerce\CatalogPricing\Records\CatalogPricingQueue as CatalogPricingQueueRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Sql;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Singleton]
class CatalogPricing
{
    private ?array $allCatalogPrices = null;

    /**
     * @param array|null $purchasableIds
     * @param CatalogPricingRule[]|null $catalogPricingRules
     * @throws \Exception
     * TODO: Migrate queue jobs and Console helpers to Laravel equivalents
     * TODO: Migrate app(Stores::class) and getCatalogPricingRules() once services migrated
     */
    public function generateCatalogPrices(?array $purchasableIds = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false, mixed $queue = null): void
    {
        $chunkSize = 1000;
        $this->setQueueProgress($queue, 10, 'Retrieving purchasables');

        $isAllPurchasables = $purchasableIds === null;

        if ($isAllPurchasables) {
            $purchasableIds = DB::table(Table::PURCHASABLES . ' as purchasables')
                ->join(\CraftCms\Cms\Database\Table::ELEMENTS . ' as e', 'e.id', '=', 'purchasables.id')
                ->whereNull('e.revisionId')
                ->whereNull('e.draftId')
                ->pluck('purchasables.id')
                ->all();
        } else {
            $allowedPurchasableIds = [];
            foreach (array_chunk($purchasableIds, 2000) as $chunk) {
                $allowed = DB::table(Table::PURCHASABLES . ' as purchasables')
                    ->join(\CraftCms\Cms\Database\Table::ELEMENTS . ' as e', 'e.id', '=', 'purchasables.id')
                    ->whereNull('e.revisionId')
                    ->whereNull('e.draftId')
                    ->whereIn('purchasables.id', $chunk)
                    ->pluck('purchasables.id')
                    ->all();
                $allowedPurchasableIds = array_merge($allowedPurchasableIds, $allowed);
            }
            $purchasableIds = $allowedPurchasableIds;
        }

        if (empty($purchasableIds)) {
            return;
        }

        $cprWithUserIds = DB::table(Table::CATALOG_PRICING_RULES_USERS)
            ->groupBy('catalogPricingRuleId')
            ->pluck('catalogPricingRuleId')
            ->all();

        $cprStartTime = microtime(true);
        if ($showConsoleOutput) {
            // TODO: Migrate to Laravel console output
            Console::stdout(PHP_EOL . 'Generating price data from catalog pricing rules... ');
        }

        $this->setQueueProgress($queue, 20, 'Generating catalog pricing data');
        $catalogPricing = [];

        // TODO: Migrate to app(Stores::class)->getAllStores() once Stores service migrated
        foreach (app(Stores::class)->getAllStores() as $store) {
            $priceByPurchasableId = DB::table(Table::PURCHASABLES_STORES)
                ->select(['purchasableId', 'basePrice', 'basePromotionalPrice'])
                ->where('storeId', $store->id)
                ->get()
                ->keyBy('purchasableId')
                ->all();

            // TODO: Migrate to app(CatalogPricingRules::class)->getAllActiveCatalogPricingRules() once registered
            $runCatalogPricingRules = $catalogPricingRules ?? app(CatalogPricingRules::class)->getAllActiveCatalogPricingRules($store->id)->all();

            foreach ($runCatalogPricingRules as $catalogPricingRule) {
                if ($catalogPricingRule->storeId !== $store->id || !$catalogPricingRule->enabled) {
                    continue;
                }

                if (!empty($catalogPricingRule->getCustomerCondition()->getConditionRules()) && !in_array($catalogPricingRule->id, $cprWithUserIds, true)) {
                    continue;
                }

                if ($catalogPricingRule->getPurchasableIds() === null) {
                    $applyPurchasableIds = $purchasableIds;
                } else {
                    $applyPurchasableIds = $isAllPurchasables
                        ? $catalogPricingRule->getPurchasableIds()
                        : array_intersect($catalogPricingRule->getPurchasableIds(), $purchasableIds);
                }

                if (empty($applyPurchasableIds)) {
                    continue;
                }

                foreach ($applyPurchasableIds as $purchasableId) {
                    if (!isset($priceByPurchasableId[$purchasableId])) {
                        continue;
                    }

                    $row = $priceByPurchasableId[$purchasableId];

                    // TODO: migrate to app(CatalogPricingRules::class)->generateRulePriceFromPrice() once registered
                    $catalogPrice = app(CatalogPricingRules::class)->generateRulePriceFromPrice(
                        $row->basePrice,
                        $row->basePromotionalPrice,
                        $catalogPricingRule
                    );

                    if ($catalogPrice === null) {
                        continue;
                    }

                    $catalogPricing[] = [
                        $purchasableId,
                        $catalogPrice,
                        $store->id,
                        $catalogPricingRule->isPromotionalPrice,
                        $catalogPricingRule->id,
                        // TODO: migrate to Laravel date helper once CraftDb::prepareDateForDb is replaced
                        $catalogPricingRule->dateFrom ? CraftDb::prepareDateForDb($catalogPricingRule->dateFrom) : null,
                        $catalogPricingRule->dateTo ? CraftDb::prepareDateForDb($catalogPricingRule->dateTo) : null,
                        false,
                    ];
                }
            }
        }

        $cprExecutionLength = microtime(true) - $cprStartTime;
        if ($showConsoleOutput) {
            Console::stdout('done!');
            Console::stdout(PHP_EOL . 'Created ' . count($catalogPricing) . ' rule price data in ' . round($cprExecutionLength, 2) . ' seconds' . PHP_EOL);
        }

        $this->setQueueProgress($queue, 40, 'Clearing existing catalog prices');

        DB::beginTransaction();

        if (!$isAllPurchasables || !empty($catalogPricingRules)) {
            foreach (array_chunk($purchasableIds, 1000) as $chunk) {
                $where = ['purchasableId' => $chunk];
                $query = DB::table(Table::CATALOG_PRICING)->whereIn('purchasableId', $chunk);

                if (!empty($catalogPricingRules)) {
                    $ruleIds = array_column($catalogPricingRules, 'id');
                    $query->whereIn('catalogPricingRuleId', $ruleIds);
                }

                $query->delete();
            }
        } else {
            DB::table(Table::CATALOG_PRICING)->truncate();
        }

        if (empty($catalogPricingRules)) {
            $this->setQueueProgress($queue, 60, 'Copying base prices to catalog pricing');
            $total = count($purchasableIds);
            $baseStateTime = microtime(true);
            $count = 1;

            $uuidFunction = Sql::uuidSql();
            $nowFunction = Sql::nowSql();

            $cpTable = Table::CATALOG_PRICING;
            $psTable = Table::PURCHASABLES_STORES;

            foreach (array_chunk($purchasableIds, $chunkSize) as $chunk) {
                $fromCount = number_format($count, 0);
                $toCount = ($count + ($chunkSize - 1)) > $total ? $total : number_format($count + count($chunk) - 1, 0);

                if ($showConsoleOutput) {
                    Console::stdout(PHP_EOL . sprintf('Generating base prices rows for purchasables %s to %s of %s... ', $fromCount, $toCount, $total));
                }

                $idList = implode(',', array_map('intval', $chunk));

                DB::statement("
                    INSERT INTO {$cpTable} (price, purchasableId, storeId, uid, dateCreated, dateUpdated)
                    SELECT basePrice, purchasableId, storeId, {$uuidFunction}, {$nowFunction}, {$nowFunction}
                    FROM {$psTable}
                    WHERE purchasableId IN ({$idList})
                ");

                DB::statement("
                    INSERT INTO {$cpTable} (price, purchasableId, storeId, isPromotionalPrice, uid, dateCreated, dateUpdated)
                    SELECT basePromotionalPrice, purchasableId, storeId, true, {$uuidFunction}, {$nowFunction}, {$nowFunction}
                    FROM {$psTable}
                    WHERE basePromotionalPrice IS NOT NULL AND purchasableId IN ({$idList})
                ");

                if ($showConsoleOutput) {
                    Console::stdout('done!');
                }
                $count += $chunkSize;
            }

            $baseExecutionLength = microtime(true) - $baseStateTime;
            if ($showConsoleOutput) {
                Console::stdout(PHP_EOL . 'Generated ' . $total . ' base prices in ' . round($baseExecutionLength, 2) . ' seconds' . PHP_EOL);
            }
        }

        $this->setQueueProgress($queue, 80, 'Inserting catalog pricing');

        if (!empty($catalogPricing)) {
            $count = 1;
            $startTime = microtime(true);
            $total = count($catalogPricing);

            foreach (array_chunk($catalogPricing, $chunkSize) as $chunk) {
                $fromCount = number_format($count, 0);
                $toCount = ($count + ($chunkSize - 1)) > $total ? number_format($total, 0) : number_format($count + count($chunk) - 1, 0);

                if ($showConsoleOutput) {
                    Console::stdout(PHP_EOL . sprintf('Inserting catalog pricing rule prices rows %s to %s of %s... ', $fromCount, $toCount, number_format($total, 0)));
                }

                DB::table(Table::CATALOG_PRICING)->insert(array_map(fn($row) => [
                    'purchasableId' => $row[0],
                    'price' => $row[1],
                    'storeId' => $row[2],
                    'isPromotionalPrice' => $row[3],
                    'catalogPricingRuleId' => $row[4],
                    'dateFrom' => $row[5],
                    'dateTo' => $row[6],
                    'hasUpdatePending' => $row[7],
                ], $chunk));

                $count += $chunkSize;

                if ($showConsoleOutput) {
                    Console::stdout('done!');
                }
            }

            $executionLength = microtime(true) - $startTime;
            if ($showConsoleOutput) {
                Console::stdout(PHP_EOL . 'Generated ' . number_format($total, 0) . ' prices in ' . round($executionLength, 2) . ' seconds' . PHP_EOL);
            }
        }

        DB::commit();

        $this->setQueueProgress($queue, 100);
    }

    public function getCatalogPrice(int $purchasableId, ?int $storeId = null, ?int $userId = null, bool $isPromotionalPrice = false): ?float
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        $userKey = $userId ?? 'all';
        $promoKey = $isPromotionalPrice ? 'promo' : 'standard';
        $key = 'catalog-price-' . implode('-', [$storeId, $userKey, $promoKey]);

        if ($this->allCatalogPrices === null || !isset($this->allCatalogPrices[$key])) {
            $result = $this->createCatalogPricesQuery($userId, $storeId)
                ->addSelect(['purchasableId'])
                ->get()
                ->keyBy('purchasableId');

            $this->allCatalogPrices[$key] = $result->pluck(
                $isPromotionalPrice ? 'promotionalPrice' : 'price',
                'purchasableId'
            )->all();
        }

        return $this->allCatalogPrices[$key][$purchasableId] ?? null;
    }

    /**
     * @return Collection<int, CatalogPricingModel>
     */
    public function getCatalogPricesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        $rows = $this->createCatalogPricesQuery(storeId: $storeId, allPrices: true)
            ->select(['id', 'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'uid'])
            ->where('cp.purchasableId', $purchasableId)
            ->whereNotNull('cp.catalogPricingRuleId')
            ->get()
            ->all();

        return collect($rows)->map(fn($row) => new CatalogPricingModel((array) $row));
    }

    /**
     * @return Collection<int, CatalogPricingModel>
     */
    public function getCatalogPrices(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, ?int $limit = null, ?int $offset = null): Collection
    {
        $rows = $this->buildCatalogPricesQuery($storeId, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset)
            ->select(['price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'cp.uid'])
            ->orderBy('purchasableId')
            ->orderBy('catalogPricingRuleId')
            ->get()
            ->all();

        return collect($rows)->map(fn($row) => new CatalogPricingModel((array) $row));
    }

    public function getCatalogPricesPageInfo(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, int $limit = 100, int $offset = 0): array
    {
        $total = $this->buildCatalogPricesQuery($storeId, $conditionBuilder, $includeBasePrices, $searchText)
            ->groupBy('purchasableId')
            ->getCountForPagination(['purchasableId']);

        return [
            'first' => $offset + 1,
            'last' => $offset + $limit,
            'total' => $total,
            'prevUrl' => null,
            'nextUrl' => null,
        ];
    }

    public function markPricesAsUpdatePending(int|array|null $catalogPricingRuleId = null, int|array|null $purchasableId = null, int|array|null $storeId = null): void
    {
        $query = DB::table(Table::CATALOG_PRICING);

        if ($catalogPricingRuleId !== null) {
            is_array($catalogPricingRuleId) ? $query->whereIn('catalogPricingRuleId', $catalogPricingRuleId) : $query->where('catalogPricingRuleId', $catalogPricingRuleId);
        }
        if ($purchasableId !== null) {
            is_array($purchasableId) ? $query->whereIn('purchasableId', $purchasableId) : $query->where('purchasableId', $purchasableId);
        }
        if ($storeId !== null) {
            is_array($storeId) ? $query->whereIn('storeId', $storeId) : $query->where('storeId', $storeId);
        }

        $query->update(['hasUpdatePending' => true]);
    }

    /**
     * @deprecated in 5.5.0
     * TODO: remove when callers have been migrated
     */
    public function afterSavePurchasableHandler(mixed $event): void
    {
        // TODO: update to new Purchasable element API once migrated
        $purchasable = $event->sender;
        if ($purchasable->propagating || $purchasable->getIsDraft() || $purchasable->getIsRevision()) {
            return;
        }

        $this->createCatalogPricingJob(['purchasableIds' => [$purchasable->id], 'storeId' => $purchasable->storeId]);
    }

    /**
     * @param int $priority Ignored — Laravel's queue has no per-dispatch priority concept. Kept for
     * backwards compatibility with callers still passing it.
     */
    public function createCatalogPricingJob(array $config = [], int $priority = 100): void
    {
        $catalogPricingRuleIds = $this->_normalizeIds($config['catalogPricingRuleIds'] ?? null);
        $purchasableIds = $this->_normalizeIds($config['purchasableIds'] ?? null);

        if ($catalogPricingRuleIds === [] && $purchasableIds === []) {
            return;
        }

        $storeId = $config['storeId'] ?? null;
        $this->markPricesAsUpdatePending($catalogPricingRuleIds, $purchasableIds, $storeId);

        // Queue purchasable-based and rule-based work into separate rows so they are never cross-contaminated.
        // Catalog pricing rules determine which purchasables are relevant, so the two must be processed independently.

        if (!empty($purchasableIds) || ($purchasableIds === null && empty($catalogPricingRuleIds))) {
            // Specific purchasable IDs: these will be regenerated against all applicable rules
            $this->_queueCatalogPricingIds($storeId, CatalogPricingQueueRecord::TYPE_PURCHASABLE, $purchasableIds);
        }

        if (!empty($catalogPricingRuleIds)) {
            $this->_queueCatalogPricingIds($storeId, CatalogPricingQueueRecord::TYPE_RULE, $catalogPricingRuleIds);
        }

        CatalogPricingJob::dispatch();
    }

    public function areCatalogPricingJobsRunning(): bool
    {
        return DB::table(Table::CATALOG_PRICING_QUEUE)->exists();
    }

    /**
     * Reserves one pending queue row for processing.
     */
    public function reserveCatalogPricingQueueRow(): ?CatalogPricingQueueRecord
    {
        $lock = Cache::lock('catalogpricingqueue', 30);

        // Use the same lock as the write methods so that reservation and inserts/merges are fully serialised.
        // Non-blocking: if a write operation is currently holding the lock, return null and let the next
        // queue job execution pick up the row instead.
        if (!$lock->get()) {
            return null;
        }

        try {
            $pendingId = DB::table(Table::CATALOG_PRICING_QUEUE)
                ->where('reserved', false)
                ->orderBy('id')
                ->value('id');

            if (!$pendingId) {
                return null;
            }

            $record = CatalogPricingQueueRecord::where('id', (int)$pendingId)
                ->where('reserved', false)
                ->first();

            if (!$record) {
                return null;
            }

            $record->reserved = true;
            $record->save();

            return $record;
        } finally {
            $lock->release();
        }
    }

    public function releaseCatalogPricingQueueRowById(int $id): void
    {
        $record = CatalogPricingQueueRecord::find($id);
        if ($record) {
            $record->reserved = false;
            $record->save();
        }
    }

    public function deleteCatalogPricingQueueRowById(int $id): void
    {
        CatalogPricingQueueRecord::where('id', $id)->delete();
    }

    /**
     * Queues catalog pricing regeneration IDs by row type, merging into any existing unreserved row
     * for the same store and type.
     *
     * @throws \RuntimeException if the queue mutex cannot be acquired
     */
    private function _queueCatalogPricingIds(?int $storeId, string $type, ?array $ids): void
    {
        $lock = Cache::lock('catalogpricingqueue', 30);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new \RuntimeException('Unable to acquire the catalog pricing queue mutex.');
        }

        try {
            // Merge into an existing unreserved row for the same store and type.
            $pendingRecord = CatalogPricingQueueRecord::where('storeId', $storeId)
                ->where('type', $type)
                ->where('reserved', false)
                ->first();

            if ($pendingRecord) {
                // Merge IDs, preserving null to represent the broader "all IDs" scope.
                $pendingIds = $pendingRecord->ids;
                $ids = ($pendingIds === null || $ids === null)
                    ? null
                    : $this->_normalizeIds(array_merge($pendingIds, $ids));

                $pendingRecord->ids = $ids;
                $pendingRecord->save();

                return;
            }

            $record = new CatalogPricingQueueRecord();
            $record->storeId = $storeId;
            $record->type = $type;
            $record->ids = $ids;
            $record->reserved = false;
            $record->save();
        } finally {
            $lock->release();
        }
    }

    private function _normalizeIds(?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }

        $ids = array_map(fn(mixed $id) => (int)$id, $ids);
        $ids = array_values(array_unique(array_filter($ids, fn(int $id) => $id > 0)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Creates a query for catalog prices, selecting price/promotionalPrice/salePrice columns.
     */
    public function createCatalogPricesQuery(?int $userId = null, int|string|null $storeId = null, bool $allPrices = false, ?CatalogPricingCondition $condition = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::CATALOG_PRICING . ' as cp')
            ->select([
                DB::raw('MIN(CASE WHEN isPromotionalPrice = FALSE THEN price END) AS price'),
                DB::raw('MIN(CASE WHEN isPromotionalPrice = TRUE THEN price END) AS promotionalPrice'),
                DB::raw('MIN(price) AS salePrice'),
            ]);

        $condition ??= Conditions::createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => $allPrices,
        ]);

        if ($userId) {
            $condition->addConditionRule(Conditions::createConditionRule([
                'class' => CatalogPricingCustomerConditionRule::class,
                'customerId' => $userId,
            ]));
        }

        /** @var CatalogPricingCondition $condition */
        $condition->modifyQuery($query);

        $query->where(function($q) {
            $q->whereNull('dateFrom')->orWhereRaw('dateFrom <= ?', [CraftDb::prepareDateForDb(new DateTime())]);
        })->where(function($q) {
            $q->whereNull('dateTo')->orWhereRaw('dateTo >= ?', [CraftDb::prepareDateForDb(new DateTime())]);
        });

        if (!$allPrices) {
            $query->groupBy(['purchasableId', 'storeId']);
        }

        if ($storeId) {
            $query->where('storeId', $storeId);
        }

        return $query;
    }

    /**
     * @deprecated in 5.1.0. Use createCatalogPricesQuery() instead.
     */
    public function createCatalogPricingQuery(?int $userId = null, int|string|null $storeId = null, ?bool $isPromotionalPrice = null, bool $allPrices = false, ?CatalogPricingCondition $condition = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::CATALOG_PRICING . ' as cp')
            ->select([DB::raw('MIN(price) as price')]);

        $condition ??= Conditions::createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => $allPrices,
        ]);

        if ($userId) {
            $condition->addConditionRule(Conditions::createConditionRule([
                'class' => CatalogPricingCustomerConditionRule::class,
                'customerId' => $userId,
            ]));
        }

        /** @var CatalogPricingCondition $condition */
        $condition->modifyQuery($query);

        $query->where(function($q) {
            $q->whereNull('dateFrom')->orWhereRaw('dateFrom <= ?', [CraftDb::prepareDateForDb(new DateTime())]);
        })->where(function($q) {
            $q->whereNull('dateTo')->orWhereRaw('dateTo >= ?', [CraftDb::prepareDateForDb(new DateTime())]);
        })->orderBy('purchasableId')->orderBy('price');

        if (!$allPrices) {
            $query->groupBy(['purchasableId', 'storeId']);
        }

        if ($storeId) {
            $query->where('storeId', $storeId);
        }

        if ($isPromotionalPrice !== null) {
            $query->where('isPromotionalPrice', $isPromotionalPrice);
        }

        return $query;
    }

    private function buildCatalogPricesQuery(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, ?int $limit = null, ?int $offset = null): \Illuminate\Database\Query\Builder
    {
        $query = $this->createCatalogPricesQuery(storeId: $storeId, allPrices: true, condition: $conditionBuilder);

        if (!$includeBasePrices) {
            $query->whereNotNull('catalogPricingRuleId');
        }

        $subQuery = DB::table(Table::PURCHASABLES)->select('id');

        if ($limit) {
            $subQuery->limit($limit);
        }
        if ($offset) {
            $subQuery->offset($offset);
        }

        if ($searchText) {
            $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $subQuery->where('description', $likeOp, '%' . $searchText . '%');
        }

        $query->joinSub($subQuery, 'purchasables', 'purchasables.id', '=', 'cp.purchasableId');

        if ($conditionBuilder !== null) {
            $conditionBuilder->modifyQuery($query);
        }

        return $query;
    }

    private function setQueueProgress(mixed $queue, float $progress, ?string $label = null): void
    {
        // TODO: migrate to Laravel queue progress interface once queue system migrated
        if (is_object($queue) && method_exists($queue, 'setProgress')) {
            $queue->setProgress((int) $progress, $label);
        }
    }
}
