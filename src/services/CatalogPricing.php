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
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
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
        $queue?->setProgress(0.1, 'Retrieving purchasables');

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

        $cprStartTime = microtime(true);
        if ($showConsoleOutput) {
            Console::stdout(PHP_EOL . 'Generating price data from catalog pricing rules... ');
        }

        $queue?->setProgress(0.2, 'Generating catalog pricing data');
        $catalogPricing = [];
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $priceByPurchasableId = (new Query())
                ->select(['purchasableId', 'basePrice', 'basePromotionalPrice'])
                ->from([Table::PURCHASABLES_STORES])
                ->where(['storeId' => $store->id])
                ->indexBy('purchasableId')
                ->all();

            $runCatalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules($store->id, false)->all();

            foreach ($runCatalogPricingRules as $catalogPricingRule) {
                // Skip rule processing if it isn't for this store.
                // This is in case incompatible rules were passed in.
                if ($catalogPricingRule->storeId !== $store->id) {
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

                    if ($catalogPricingRule->purchasableId && $purchasableId != $catalogPricingRule->purchasableId) {
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
                    ];
                }
            }
        }

        $cprExecutionLength = microtime(true) - $cprStartTime;
        if ($showConsoleOutput) {
            Console::stdout('done!');
            Console::stdout(PHP_EOL . 'Created ' . count($catalogPricing) . ' rule price data in ' . round($cprExecutionLength, 2) . ' seconds' . PHP_EOL);
        }

        $queue?->setProgress(0.4, 'Clearing existing catalog prices');
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
            $queue?->setProgress(0.6, 'Copying base prices to catalog pricing');
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

                Craft::$app->getDb()->createCommand()->setSql('
    INSERT INTO [[commerce_catalogpricing]] ([[price]], [[purchasableId]], [[storeId]], [[uid]], [[dateCreated]], [[dateUpdated]])
    SELECT [[basePrice]], [[purchasableId]], [[storeId]], UUID(), NOW(), NOW() FROM [[commerce_purchasables_stores]]
    WHERE [[purchasableId]] IN (' . implode(',', $purchasableIdsChunk) . ')
                ')->execute();
                Craft::$app->getDb()->createCommand()->setSql('
    INSERT INTO [[commerce_catalogpricing]] ([[price]], [[purchasableId]], [[storeId]], [[isPromotionalPrice]], [[uid]], [[dateCreated]], [[dateUpdated]])
    SELECT [[basePromotionalPrice]], [[purchasableId]], [[storeId]], 1, UUID(), NOW(), NOW() FROM [[commerce_purchasables_stores]]
    WHERE (NOT ([[basePromotionalPrice]] is null)) AND [[purchasableId]] IN (' . implode(',', $purchasableIdsChunk) . ')
                ')->execute();
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

        $queue?->setProgress(0.8, 'Inserting catalog pricing');
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
        $queue?->setProgress(1);
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
                'id', 'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'uid'
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
     * @param int $limit
     * @param int $offset
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getCatalogPrices(int $storeId, ?CatalogPricingCondition $conditionBuilder = null, ?string $searchText = null, int $limit = 100, int $offset = 0): Collection
    {
        $query = Plugin::getInstance()->getCatalogPricing()->createCatalogPricingQuery(storeId: $storeId, allPrices: true, condition: $conditionBuilder)
            ->select([
                'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'cp.uid'
            ]);

        if ($searchText) {
            $query->innerJoin(Table::PURCHASABLES . ' purchasables', 'cp.purchasableId = purchasables.id');
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ilike' : 'like';
            $query->andWhere([$likeOperator, 'purchasables.description', $searchText]);
        }

        // If there is a condition builder, modify the query
        $conditionBuilder?->modifyQuery($query);

        // @TODO pagination/limit
        $query->limit($limit);
        $query->offset($offset);

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

        QueueHelper::push(Craft::createObject([
            'class' => CatalogPricingJob::class,
            'purchasableIds' => [$event->sender->id],
        ]), 100);
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
