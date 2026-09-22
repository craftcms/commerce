<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing;

use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricing as CatalogPricingModel;
use CraftCms\Commerce\CatalogPricing\Jobs\CatalogPricingJob;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingQueue as CatalogPricingQueueRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Sql;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;

#[Scoped]
class CatalogPricing
{
    private ?array $allCatalogPrices = null;

    public function generateCatalogPrices(?array $purchasableIds = null, ?array $catalogPricingRules = null, ?OutputInterface $output = null, mixed $queue = null): void
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
        $output?->write(PHP_EOL . 'Generating price data from catalog pricing rules... ');

        $this->setQueueProgress($queue, 20, 'Generating catalog pricing data');
        $catalogPricing = [];

        foreach (app(Stores::class)->getAllStores() as $store) {
            $priceByPurchasableId = DB::table(Table::PURCHASABLES_STORES)
                ->select(['purchasableId', 'basePrice', 'basePromotionalPrice'])
                ->where('storeId', $store->id)
                ->get()
                ->keyBy('purchasableId')
                ->all();

            $runCatalogPricingRules = $catalogPricingRules ?? app(CatalogPricingRules::class)->getAllActiveCatalogPricingRules($store->id)->all();

            foreach ($runCatalogPricingRules as $catalogPricingRule) {
                if ($catalogPricingRule->storeId !== $store->id || !$catalogPricingRule->enabled) {
                    continue;
                }

                if (!empty($catalogPricingRule->getCustomerCondition()->getConditionRules()->getRules()) && !in_array($catalogPricingRule->id, $cprWithUserIds, true)) {
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
                        $catalogPricingRule->dateFrom ? \CraftCms\Cms\Support\Query::prepareDateForDb($catalogPricingRule->dateFrom) : null,
                        $catalogPricingRule->dateTo ? \CraftCms\Cms\Support\Query::prepareDateForDb($catalogPricingRule->dateTo) : null,
                        false,
                    ];
                }
            }
        }

        $cprExecutionLength = microtime(true) - $cprStartTime;
        $output?->write('done!');
        $output?->write(PHP_EOL . 'Created ' . count($catalogPricing) . ' rule price data in ' . round($cprExecutionLength, 2) . ' seconds' . PHP_EOL);

        $this->setQueueProgress($queue, 40, 'Clearing existing catalog prices');

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
            // TRUNCATE is DDL and causes an implicit commit on MySQL/MariaDB, so it must run
            // before the transaction below is opened - doing it inside would silently end the
            // transaction, leaving the later DB::commit() with nothing to commit.
            DB::table(Table::CATALOG_PRICING)->truncate();
        }

        DB::beginTransaction();

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

                $output?->write(PHP_EOL . sprintf('Generating base prices rows for purchasables %s to %s of %s... ', $fromCount, $toCount, $total));

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

                $output?->write('done!');
                $count += $chunkSize;
            }

            $baseExecutionLength = microtime(true) - $baseStateTime;
            $output?->write(PHP_EOL . 'Generated ' . $total . ' base prices in ' . round($baseExecutionLength, 2) . ' seconds' . PHP_EOL);
        }

        $this->setQueueProgress($queue, 80, 'Inserting catalog pricing');

        if (!empty($catalogPricing)) {
            $count = 1;
            $startTime = microtime(true);
            $total = count($catalogPricing);

            foreach (array_chunk($catalogPricing, $chunkSize) as $chunk) {
                $fromCount = number_format($count, 0);
                $toCount = ($count + ($chunkSize - 1)) > $total ? number_format($total, 0) : number_format($count + count($chunk) - 1, 0);

                $output?->write(PHP_EOL . sprintf('Inserting catalog pricing rule prices rows %s to %s of %s... ', $fromCount, $toCount, number_format($total, 0)));

                DB::table(Table::CATALOG_PRICING)->insert(array_map(fn($row) => [
                    'purchasableId' => $row[0],
                    'price' => $row[1],
                    'storeId' => $row[2],
                    'isPromotionalPrice' => $row[3],
                    'catalogPricingRuleId' => $row[4],
                    'dateFrom' => $row[5],
                    'dateTo' => $row[6],
                    'hasUpdatePending' => $row[7],
                    'uid' => Str::uuid()->toString(),
                    'dateCreated' => now()->toDateTimeString(),
                    'dateUpdated' => now()->toDateTimeString(),
                ], $chunk));

                $count += $chunkSize;

                $output?->write('done!');
            }

            $executionLength = microtime(true) - $startTime;
            $output?->write(PHP_EOL . 'Generated ' . number_format($total, 0) . ' prices in ' . round($executionLength, 2) . ' seconds' . PHP_EOL);
        }

        DB::commit();

        $this->setQueueProgress($queue, 100);
    }

    public function getCatalogPrice(int $purchasableId, ?int $storeId = null, ?int $userId = null, bool $isPromotionalPrice = false): ?float
    {
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
        // getCountForPagination() wraps the query as-is and counts a column from its SELECT —
        // but this query only ever selects price/promotionalPrice/salePrice aggregates, never
        // purchasableId, so that column was never there to count. select()ing it first (like
        // the pre-port Yii2 version did) replaces the aggregate select instead of layering atop
        // it, giving a plain "one row per purchasableId" result to just count.
        $total = $this->buildCatalogPricesQuery($storeId, $conditionBuilder, $includeBasePrices, $searchText)
            ->select(['purchasableId'])
            ->groupBy('purchasableId')
            ->get()
            ->count();

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
        // Quoted via the connection's own grammar, not embedded as bare SQL — the column is
        // camelCase, and an unquoted raw fragment gets folded to lowercase by Postgres.
        $isPromotionalPrice = DB::connection()->getQueryGrammar()->wrap('isPromotionalPrice');

        $query = DB::table(Table::CATALOG_PRICING . ' as cp')
            ->select([
                DB::raw(Sql::decimalSql('MIN(CASE WHEN isPromotionalPrice = FALSE THEN price END)') . ' AS price'),
                DB::raw(Sql::decimalSql('MIN(CASE WHEN isPromotionalPrice = TRUE THEN price END)') . ' AS promotionalPrice'),
                DB::raw(Sql::decimalSql('MIN(price)') . ' AS salePrice'),
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

        // Plain where()s rather than whereRaw() — the column is camelCase, and an unquoted
        // raw fragment gets folded to lowercase by Postgres, no longer matching it.
        $query->where(function($q) {
            $q->whereNull('dateFrom')->orWhere('dateFrom', '<=', CraftDb::prepareDateForDb(new DateTime()));
        })->where(function($q) {
            $q->whereNull('dateTo')->orWhere('dateTo', '>=', CraftDb::prepareDateForDb(new DateTime()));
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
            $q->whereNull('dateFrom')->orWhere('dateFrom', '<=', CraftDb::prepareDateForDb(new DateTime()));
        })->where(function($q) {
            $q->whereNull('dateTo')->orWhere('dateTo', '>=', CraftDb::prepareDateForDb(new DateTime()));
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

    /**
     * $queue is deliberately untyped: it's either a `CraftCms\Cms\Queue\Job` instance (this
     * class's own createCatalogPricingJob()/CatalogPricingJob::handle() pass `$this`, which
     * widens the base class's normally-protected setProgress() to public), or a legacy
     * `yii\queue\Queue`/`craft\queue\QueueInterface` instance forwarded by the deprecated
     * `craft\commerce\services\CatalogPricing::generateCatalogPrices()` wrapper for
     * backwards-compatible callers. Both happen to expose a compatible setProgress(int, ?string)
     * method, but share no common interface to type-hint against, and the new Job base class's
     * own setProgress() isn't public - only CatalogPricingJob's override is - so this can't be
     * tightened without either breaking that legacy call path or making Job::setProgress()
     * public for every job.
     */
    private function setQueueProgress(mixed $queue, float $progress, ?string $label = null): void
    {
        if (is_object($queue) && method_exists($queue, 'setProgress')) {
            $queue->setProgress((int) $progress, $label);
        }
    }
}
