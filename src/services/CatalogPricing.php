<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\purchasables\CatalogPricingCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingCustomerConditionRule;
use craft\commerce\models\CatalogPricing as CatalogPricingModel;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\CatalogPricing as CatalogPricingJob;
use craft\db\Query;
use craft\errors\SiteNotFoundException;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\helpers\Queue as QueueHelper;
use craft\queue\Queue;
use craft\queue\QueueInterface;
use DateTime;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Catalog Pricing service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricing extends Component
{
    /**
     * @var array|null
     */
    private ?array $_allCatalogPrices = null;

    private function setQueueProgress(Queue|QueueInterface|null $queue, float $progress, ?string $label = null): void
    {
        if ($queue instanceof QueueInterface) {
            $queue->setProgress((int)$progress, $label);
        }
    }

    /**
     * @param array|null $purchasableIds
     * @param CatalogPricingRule[]|null $catalogPricingRules
     * @param bool $showConsoleOutput
     * @param Queue|QueueInterface|null $queue
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function generateCatalogPrices(?array $purchasableIds = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false, Queue|QueueInterface $queue = null): void
    {
        $chunkSize = 1000;
        $this->setQueueProgress($queue, 10, 'Retrieving purchasables');

        $isAllPurchasables = $purchasableIds === null;
        if ($isAllPurchasables) {
            $purchasableIds = (new Query())
                ->select(['id'])
                ->from([Table::PURCHASABLES])
                ->column();
        }

        if (empty($purchasableIds)) {
            return;
        }

        // @TODO maybe mark prices as pending update here?

        $cprStartTime = microtime(true);
        if ($showConsoleOutput) {
            Console::stdout(PHP_EOL . 'Generating price data from catalog pricing rules... ');
        }

        $this->setQueueProgress($queue, 20, 'Generating catalog pricing data');
        $catalogPricing = [];
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $priceByPurchasableId = (new Query())
                ->select(['purchasableId', 'basePrice', 'basePromotionalPrice'])
                ->from([Table::PURCHASABLES_STORES])
                ->where(['storeId' => $store->id])
                ->indexBy('purchasableId')
                ->all();

            $runCatalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules($store->id)->all();

            foreach ($runCatalogPricingRules as $catalogPricingRule) {
                // Skip rule processing if it isn't for this store.
                // This is in case incompatible rules were passed in.
                if ($catalogPricingRule->storeId !== $store->id) {
                    continue;
                }

                // Skip rule if the rule is not enabled
                if (!$catalogPricingRule->enabled) {
                    continue;
                }

                // If `getPurchasableIds()` is `null` this means all purchasables
                if ($catalogPricingRule->getPurchasableIds() === null) {
                    $applyPurchasableIds = $purchasableIds;
                } else {
                    $applyPurchasableIds = $isAllPurchasables ? $catalogPricingRule->getPurchasableIds() : array_intersect($catalogPricingRule->getPurchasableIds(), $purchasableIds);
                }

                if (empty($applyPurchasableIds)) {
                    continue;
                }

                foreach ($applyPurchasableIds as $purchasableId) {
                    if (!isset($priceByPurchasableId[$purchasableId])) {
                        continue;
                    }

                    $catalogPrice = Plugin::getInstance()->getCatalogPricingRules()->generateRulePriceFromPrice($priceByPurchasableId[$purchasableId]['basePrice'], $priceByPurchasableId[$purchasableId]['basePromotionalPrice'], $catalogPricingRule);

                    if ($catalogPrice === null) {
                        continue;
                    }

                    $catalogPricing[] = [
                        $purchasableId, // purchasableId
                        $catalogPrice, // price
                        $store->id, // storeId
                        $catalogPricingRule->isPromotionalPrice, // isPromotionalPrice
                        $catalogPricingRule->id, // catalogPricingRuleId
                        $catalogPricingRule->dateFrom ? Db::prepareDateForDb($catalogPricingRule->dateFrom) : null, // dateFrom
                        $catalogPricingRule->dateTo ? Db::prepareDateForDb($catalogPricingRule->dateTo) : null, // dateTo
                        false, // hasUpdatePending
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
        $transaction = Craft::$app->getDb()->beginTransaction();
        // Truncate the catalog pricing table
        if (!$isAllPurchasables || !empty($catalogPricingRules)) {
            // If purchasable IDs are passed in or catalog pricing rules are passed in
            // only delete the rows for those purchasable IDs and catalog pricing rules
            foreach (array_chunk($purchasableIds, 1000) as $purchasableIdsChunk) {
                $where = ['purchasableId' => $purchasableIdsChunk];

                // If passing catalog pricing rules only delete the rows for those rules
                if (!empty($catalogPricingRules)) {
                    $where['catalogPricingRuleId'] = ArrayHelper::getColumn($catalogPricingRules, 'id');
                }

                Craft::$app->getDb()->createCommand()
                    ->delete(Table::CATALOG_PRICING, $where)
                    ->execute();
            }
        } else {
            Craft::$app->getDb()->createCommand()->truncateTable(Table::CATALOG_PRICING)->execute();
        }

        // If there are no specific catalog pricing rules passed in then copy the base prices into the catalog pricing table
        if (empty($catalogPricingRules)) {
            $this->setQueueProgress($queue, 60, 'Copying base prices to catalog pricing');
            $total = count($purchasableIds);
            $baseStateTime = microtime(true);
            $count = 1;
            // Copy base prices into the catalog pricing table with a query for speed
            // Batch through the purchasable IDs as we don't know what is passed in and don't want to hit the int limit in the where clause
            foreach (array_chunk($purchasableIds, $chunkSize) as $purchasableIdsChunk) {
                $fromCount = Craft::$app->getFormatter()->asDecimal($count, 0);
                $toCount = ($count + ($chunkSize - 1)) > count($purchasableIds) ? $total : Craft::$app->getFormatter()->asDecimal($count + count($purchasableIdsChunk) - 1, 0);
                if ($showConsoleOutput) {
                    Console::stdout(PHP_EOL . sprintf('Generating base prices rows for purchasables %s to %s of %s... ', $fromCount, $toCount, $total));
                }

                $uuidFunction = Craft::$app->getDb()->getIsPgsql() ? 'gen_random_uuid()' : 'UUID()';

                $schema = Craft::$app->getDb()->getSchema();
                $catalogPricingTable = $schema->getRawTableName(Table::CATALOG_PRICING);
                $commercePurchasablesStoresTable = $schema->getRawTableName(Table::PURCHASABLES_STORES);

                $insert = Craft::$app->getDb()->createCommand()->setSql('
    INSERT INTO [[' . $catalogPricingTable . ']] ([[price]], [[purchasableId]], [[storeId]], [[uid]], [[dateCreated]], [[dateUpdated]])
    SELECT [[basePrice]], [[purchasableId]], [[storeId]], ' . $uuidFunction . ', NOW(), NOW() FROM [[' . $commercePurchasablesStoresTable . ']]
    WHERE [[purchasableId]] IN (' . implode(',', $purchasableIdsChunk) . ')
                ');
                $insert->execute();

                $insert = Craft::$app->getDb()->createCommand()->setSql('
    INSERT INTO [[' . $catalogPricingTable . ']] ([[price]], [[purchasableId]], [[storeId]], [[isPromotionalPrice]], [[uid]], [[dateCreated]], [[dateUpdated]])
    SELECT [[basePromotionalPrice]], [[purchasableId]], [[storeId]], true, ' . $uuidFunction . ', NOW(), NOW() FROM [[' . $commercePurchasablesStoresTable . ']]
    WHERE (NOT ([[basePromotionalPrice]] is null)) AND [[purchasableId]] IN (' . implode(',', $purchasableIdsChunk) . ')
                ');
                $insert->execute();

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
        // Batch through `$catalogPricing` and insert into the catalog pricing table
        if (!empty($catalogPricing)) {
            $count = 1;
            $startTime = microtime(true);
            $total = Craft::$app->getFormatter()->asDecimal(count($catalogPricing), 0);
            foreach (array_chunk($catalogPricing, $chunkSize) as $catalogPricingChunk) {
                $fromCount = Craft::$app->getFormatter()->asDecimal($count, 0);
                $toCount = ($count + ($chunkSize - 1)) > count($catalogPricing) ? $total : Craft::$app->getFormatter()->asDecimal($count + count($catalogPricingChunk) - 1, 0);
                if ($showConsoleOutput) {
                    Console::stdout(PHP_EOL . sprintf('Inserting catalog pricing rule prices rows %s to %s of %s... ', $fromCount, $toCount, $total));
                }
                Craft::$app->getDb()->createCommand()->batchInsert(Table::CATALOG_PRICING, [
                    'purchasableId',
                    'price',
                    'storeId',
                    'isPromotionalPrice',
                    'catalogPricingRuleId',
                    'dateFrom',
                    'dateTo',
                    'hasUpdatePending',
                ], $catalogPricingChunk)->execute();
                $count += $chunkSize;
                if ($showConsoleOutput) {
                    Console::stdout('done!');
                }
            }

            $executionLength = microtime(true) - $startTime;
            if ($showConsoleOutput) {
                Console::stdout(PHP_EOL . 'Generated ' . $total . ' prices in ' . round($executionLength, 2) . ' seconds' . PHP_EOL);
            }
        }

        $transaction->commit();
        $this->setQueueProgress($queue, 100);
    }

    /**
     * Return the catalog price for a purchasable.
     *
     * @param int $purchasableId
     * @param int|null $storeId
     * @param int|null $userId
     * @param bool $isPromotionalPrice
     * @return float|null
     * @throws InvalidConfigException
     */
    public function getCatalogPrice(int $purchasableId, ?int $storeId = null, ?int $userId = null, bool $isPromotionalPrice = false): ?float
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;
        $userKey = $userId ?? 'all';
        $promoKey = $isPromotionalPrice ? 'promo' : 'standard';
        $key = 'catalog-price-' . implode('-', [$storeId, $userKey, $promoKey]);

        if ($this->_allCatalogPrices === null || !isset($this->_allCatalogPrices[$key])) {
            $query = $this->createCatalogPricingQuery($userId, $storeId, $isPromotionalPrice)
                ->indexBy('purchasableId');

            $this->_allCatalogPrices[$key] = $query->column();
        }

        return $this->_allCatalogPrices[$key][$purchasableId] ?? null;
    }

    /**
     * @param int $purchasableId
     * @param int|null $storeId
     * @return Collection<CatalogPricingModel>
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getCatalogPricesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $allPriceRows = $this->createCatalogPricingQuery(storeId: $storeId, allPrices: true)
            ->select([
                'id', 'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'uid',
            ])
            ->andWhere(['purchasableId' => $purchasableId])
            ->andWhere(['not', ['catalogPricingRuleId' => null]])
            ->all();

        $allPrices = [];
        foreach ($allPriceRows as $catalogPrice) {
            $allPrices[] = Craft::createObject(['class' => CatalogPricingModel::class, 'attributes' => $catalogPrice]);
        }

        return collect($allPrices);
    }

    /**
     * @param int $storeId
     * @param CatalogPricingCondition|null $conditionBuilder
     * @param string|null $searchText
     * @param int|null $limit
     * @param int|null $offset
     * @param bool $includeBasePrices
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getCatalogPrices(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, ?int $limit = null, ?int $offset = null): Collection
    {
        $query = $this->_createCatalogPricesQuery($storeId, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset)
            ->select([
                'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'cp.uid',
            ]);

        $query->orderBy('purchasableId ASC, catalogPricingRuleId ASC');
        $results = $query->all();

        $catalogPrices = [];
        foreach ($results as $result) {
            $catalogPrices[] = Craft::createObject([
                'class' => CatalogPricingModel::class,
                'attributes' => $result,
            ]);
        }

        return collect($catalogPrices);
    }

    public function getCatalogPricesPageInfo(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, int $limit = 100, int $offset = 0)
    {
        $results = $this->_createCatalogPricesQuery($storeId, $conditionBuilder, $includeBasePrices, $searchText)
            ->select(['purchasableId'])
            ->groupBy(['purchasableId'])
            ->all();

        $total = count($results);

        return [
            'first' => $offset + 1,
            'last' => $offset + $limit,
            'total' => $total,
            'prevUrl' => null,
            'nextUrl' => null,
        ];
    }

    /**
     * @param int|array|null $catalogPricingRuleId
     * @param int|array|null $purchasableId
     * @param int|array|null $storeId
     * @return void
     * @throws Exception
     */
    public function markPricesAsUpdatePending(int|array|null $catalogPricingRuleId = null, int|array|null $purchasableId = null, int|array|null $storeId = null): void
    {
        $conditions = [];

        if ($catalogPricingRuleId !== null) {
            $conditions['catalogPricingRuleId'] = $catalogPricingRuleId;
        }

        if ($purchasableId !== null) {
            $conditions['purchasableId'] = $purchasableId;
        }

        if ($storeId !== null) {
            $conditions['storeId'] = $storeId;
        }

        Craft::$app->getDb()->createCommand()
            ->update(Table::CATALOG_PRICING, ['hasUpdatePending' => true], $conditions)
            ->execute();
    }

    /**
     * @param int $storeId
     * @param CatalogPricingCondition|null $conditionBuilder
     * @param string|null $searchText
     * @param bool $includeBasePrices
     * @param int|null $limit
     * @param int|null $offset
     * @return Query
     * @throws InvalidConfigException
     */
    private function _createCatalogPricesQuery(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, bool $includeBasePrices = true, ?string $searchText = null, ?int $limit = null, ?int $offset = null): Query
    {
        $query = Plugin::getInstance()->getCatalogPricing()->createCatalogPricingQuery(storeId: $storeId, allPrices: true, condition: $conditionBuilder);

        if ($includeBasePrices === false) {
            $query->andWhere(['not', ['catalogPricingRuleId' => null]]);
        }

        $subQuery = (new Query())
            ->from(Table::PURCHASABLES)
            ->select(['id']);

        if ($limit) {
            $subQuery->limit($limit);
        }

        if ($offset) {
            $subQuery->offset($offset);
        }

        if ($searchText) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ilike' : 'like';
            $subQuery->andWhere([$likeOperator, 'purchasables.description', $searchText]);
        }

        $query->innerJoin(['purchasables' => $subQuery], '[[purchasables.id]] = [[cp.purchasableId]]');

        // If there is a condition builder, modify the query
        $conditionBuilder?->modifyQuery($query);

        return $query;
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function afterSavePurchasableHandler(ModelEvent $event): void
    {
        if (!$event->sender instanceof Purchasable || $event->sender->propagating) {
            return;
        }

        $this->createCatalogPricingJob(['purchasableIds' => [$event->sender->id]]);
    }

    /**
     * @param array $config
     * @param int $priority
     * @return void
     * @throws InvalidConfigException
     */
    public function createCatalogPricingJob(array $config = [], int $priority = 100): void
    {
        // Mark prices as pending when creating a job
        // pick out `purchasableIds`, `catalogPricingRuleIds` and `storeId` from config into new array
        $catalogPricingRuleIds = $config['catalogPricingRuleIds'] ?? null;
        $purchasableIds = $config['purchasableIds'] ?? null;
        $storeId = $config['storeId'] ?? null;
        $this->markPricesAsUpdatePending($catalogPricingRuleIds, $purchasableIds, $storeId);

        $config = array_merge([
            'class' => CatalogPricingJob::class,
        ], $config);

        $job = Craft::createObject($config);
        QueueHelper::push($job, 100);

        $jobsCache = Craft::$app->getCache()->get('catalog-pricing-jobs');
        if ($jobsCache === false) {
            $jobsCache = 0;
        }

        $jobsCache += 1;

        Craft::$app->getCache()->set('catalog-pricing-jobs', $jobsCache, 0);
    }

    /**
     * @param CatalogPricingJob $catalogPricingJob
     * @return void
     */
    public function clearCatalogPricingJob(CatalogPricingJob $catalogPricingJob): void
    {
        $jobsCache = Craft::$app->getCache()->get('catalog-pricing-jobs');
        if ($jobsCache === false) {
            return;
        }

        $jobsCache -= 1;

        Craft::$app->getCache()->set('catalog-pricing-jobs', $jobsCache, 0);
    }

    /**
     * @return bool
     */
    public function areCatalogPricingJobsRunning(): bool
    {
        if (empty(Craft::$app->getCache()->get('catalog-pricing-jobs'))) {
            return false;
        }

        return true;
    }

    /**
     * Creates query for catalog pricing.
     *
     * @param int|null $userId
     * @param int|string|null $storeId
     * @param bool|null $isPromotionalPrice
     * @return Query
     */
    public function createCatalogPricingQuery(?int $userId = null, int|string|null $storeId = null, ?bool $isPromotionalPrice = null, bool $allPrices = false, ?CatalogPricingCondition $condition = null): Query
    {
        $query = (new Query())
            ->select([new Expression('MIN(price) as price')])
            ->from([Table::CATALOG_PRICING . ' cp']);

        // Use condition builder to tweak the query for reusability
        $condition = $condition ?? Craft::$app->getConditions()->createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => $allPrices,
        ]);

        if ($userId) {
            $condition->addConditionRule(Craft::$app->getConditions()->createConditionRule([
                'class' => CatalogPricingCustomerConditionRule::class,
                'customerId' => $userId,
            ]));
        }

        $condition->modifyQuery($query);

        $query
            ->andWhere(['or', ['dateFrom' => null], ['<=', 'dateFrom', Db::prepareDateForDb(new DateTime())]])
            ->andWhere(['or', ['dateTo' => null], ['>=', 'dateTo', Db::prepareDateForDb(new DateTime())]])
            ->orderBy(['purchasableId' => SORT_ASC, 'price' => SORT_ASC]);

        // If we're not getting all prices, we need to group by purchasableId and storeId
        if (!$allPrices) {
            $query->groupBy(['purchasableId', 'storeId']);
        }

        if ($storeId) {
            $query->andWhere(['storeId' => $storeId]);
        }

        if ($isPromotionalPrice !== null) {
            $query->andWhere(['isPromotionalPrice' => $isPromotionalPrice]);
        }

        return $query;
    }
}
