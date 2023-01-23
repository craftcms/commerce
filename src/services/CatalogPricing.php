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
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\Db;
use DateTime;
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
     * @param array|null $purchasables
     * @param CatalogPricingRule[]|null $catalogPricingRules
     * @param bool $showConsoleOutput
     * @return void
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function generateCatalogPrices(?array $purchasableIds = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false): void
    {
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
        $catalogPricing = [];
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $priceByPurchasableId = (new Query())
                ->select(['purchasableId', 'basePrice', 'basePromotionalPrice'])
                ->from([Table::PURCHASABLES_STORES])
                ->where(['storeId' => $store->id])
                ->indexBy('purchasableId')
                ->all();

            $runCatalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules($store)->all();

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

                    $price = null;
                    // A third option may be required for catalog pricing rules that allow store admins to select `salePrice`.
                    // So that just want to create a catalog price from the `price` or the `promotionalPrice` if there is one.
                    if ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE) {
                        $price = $priceByPurchasableId[$purchasableId]['basePrice'];
                    } elseif ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE) {
                        // Skip if there is no promotional price
                        if ($priceByPurchasableId[$purchasableId]['basePromotionalPrice'] === null) {
                            continue;
                        }
                        $price = $priceByPurchasableId[$purchasableId]['basePromotionalPrice'];
                    }

                    if ($price === null) {
                        continue;
                    }

                    $catalogPricing[] = [
                        $purchasableId, // purchasableId
                        $catalogPricingRule->getRulePriceFromPrice($price), // price
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

        $transaction = Craft::$app->getDb()->beginTransaction();
        // Truncate the catalog pricing table
        if (!$isAllPurchasables) {
            // Batch through purchasable IDs and delete them
            foreach (array_chunk($purchasableIds, 1000) as $purchasableIdsChunk) {
                // Delete base prices from the catalog pricing table
                $where = ['purchasableId' => $purchasableIdsChunk, 'catalogPricingRuleId' => null];
                Craft::$app->getDb()->createCommand()
                    ->delete(Table::CATALOG_PRICING, $where)
                    ->execute();

                // Delete catalog pricing rules price from the catalog pricing table if rules were passed to the method
                if (!empty($catalogPricingRules)) {
                    $where['catalogPricingRuleId'] = ArrayHelper::getColumn($catalogPricingRules, 'id');
                    Craft::$app->getDb()->createCommand()
                        ->delete(Table::CATALOG_PRICING, $where)
                        ->execute();
                }
            }
        } else {
            Craft::$app->getDb()->createCommand()->truncateTable(Table::CATALOG_PRICING)->execute();
        }

        $chunkSize = 1000;
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
INSERT INTO [[commerce_catalogpricing]] ([[price]], [[purchasableId]], [[storeId]], [[dateCreated]], [[dateUpdated]])
SELECT [[basePrice]], [[purchasableId]], [[storeId]], NOW(), NOW() FROM [[commerce_purchasables_stores]]
WHERE [[purchasableId]] IN (' . implode(',', $purchasableIdsChunk) . ')
            ')->execute();
            Craft::$app->getDb()->createCommand()->setSql('
INSERT INTO [[commerce_catalogpricing]] ([[price]], [[purchasableId]], [[storeId]], [[isPromotionalPrice]], [[dateCreated]], [[dateUpdated]])
SELECT [[basePromotionalPrice]], [[purchasableId]], [[storeId]], 1, NOW(), NOW() FROM [[commerce_purchasables_stores]]
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
     * Creates query for catalog pricing.
     *
     * @param int|null $userId
     * @param int|null $storeId
     * @param bool|null $isPromotionalPrice
     * @return Query
     */
    public function createCatalogPricingQuery(?int $userId = null, int|string|null $storeId = null, ?bool $isPromotionalPrice = null): Query
    {
        $catalogPricingRuleIdWhere = [
            'or',
            ['catalogPricingRuleId' => null],
            ['catalogPricingRuleId' => (new Query())
                ->select(['cpr.id as cprid'])
                ->from([Table::CATALOG_PRICING_RULES . ' cpr'])
                ->leftJoin([Table::CATALOG_PRICING_RULES_USERS . ' cpru'], '[[cpr.id]] = [[cpru.catalogPricingRuleId]]')
                ->where(['[[cpru.id]]' => null])
                ->groupBy(['[[cpr.id]]']),
            ],
        ];
        // Sub query to figure out which catalog pricing rules are using user conditions
        if ($userId) {
            $catalogPricingRuleIdWhere[] = ['catalogPricingRuleId' => (new Query())
                ->select(['cpr.id as cprid'])
                ->from([Table::CATALOG_PRICING_RULES . ' cpr'])
                ->leftJoin([Table::CATALOG_PRICING_RULES_USERS . ' cpru'], '[[cpr.id]] = [[cpru.catalogPricingRuleId]]')
                ->where(['[[cpru.userId]]' => $userId])
                ->andWhere(['not', ['[[cpru.id]]' => null]])
                ->groupBy(['[[cpr.id]]']), ];
        }

        $query = (new Query())
            ->select([new Expression('MIN(price) as price')])
            ->from([Table::CATALOG_PRICING . ' cp'])
            ->where($catalogPricingRuleIdWhere)
            ->andWhere(['storeId' => $storeId])
            ->andWhere(['or', ['dateFrom' => null], ['<=', 'dateFrom', Db::prepareDateForDb(new DateTime())]])
            ->andWhere(['or', ['dateTo' => null], ['>=', 'dateTo', Db::prepareDateForDb(new DateTime())]])
            ->groupBy(['purchasableId'])
            ->orderBy(['purchasableId' => SORT_ASC, 'price' => SORT_ASC]);

        if ($isPromotionalPrice !== null) {
            $query->andWhere(['isPromotionalPrice' => $isPromotionalPrice]);
        }

        return $query;
    }
}
