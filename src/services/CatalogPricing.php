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
use craft\commerce\records\CatalogPricing as CatalogPricingRecord;
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
    public function generateCatalogPrices(?array $purchasables = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false): void
    {
        $isAllPurchasables = $purchasables === null;
        if ($isAllPurchasables) {
            $purchasableElementTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();
            if (empty($purchasableElementTypes)) {
                return;
            }

            $purchasables = [];
            foreach ($purchasableElementTypes as $purchasableElementType) {
                $query = Craft::$app->getElements()->createElementQuery($purchasableElementType);

                $foundPurchasables = $query->all();
                if (empty($foundPurchasables)) {
                    continue;
                }

                $purchasables = [...$purchasables, ...$foundPurchasables];
            }
        }

        if (empty($purchasables)) {
            return;
        }

        $catalogPricing = [];
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $priceByPurchasableId = (new Query())
                ->select(['purchasableId', 'price', 'promotionalPrice'])
                ->from([Table::PURCHASABLES_STORES])
                ->where(['storeId' => $store->id])
                ->indexBy('purchasableId')
                ->all();

            // Generate original pricing records
            foreach ($purchasables as $purchasable) {
                if (!isset($priceByPurchasableId[$purchasable->id]) || !isset($priceByPurchasableId[$purchasable->id]['price'])) {
                    continue;
                }

                $catalogPricing[] = [
                    $purchasable->id, // purchasableId
                    $priceByPurchasableId[$purchasable->id]['price'], // price
                    $store->id, // storeId
                    0, // isPromotionalPrice
                    null, // catalogPricingRuleId
                    null, // dateFrom
                    null, // dateTo
                ];

                if ($priceByPurchasableId[$purchasable->id]['promotionalPrice'] !== null) {
                    $catalogPricing[] = [
                        $purchasable->id, // purchasableId
                        $priceByPurchasableId[$purchasable->id]['promotionalPrice'], // price
                        $store->id, // storeId
                        1, // isPromotionalPrice
                        null, // catalogPricingRuleId
                        null, // dateFrom
                        null, // dateTo
                    ];
                }
            }

            $catalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules($store)->all();

            foreach ($catalogPricingRules as $catalogPricingRule) {
                // If `getPurchasableIds()` is `null` this means all purchasables
                if ($catalogPricingRule->getPurchasableIds() === null) {
                    $purchasableIds = ArrayHelper::getColumn($purchasables, 'id');
                } else {
                    $purchasableIds = $isAllPurchasables ? $catalogPricingRule->getPurchasableIds() : array_intersect($catalogPricingRule->getPurchasableIds(), ArrayHelper::getColumn($purchasables, 'id'));
                }

                if (empty($purchasableIds)) {
                    continue;
                }

                foreach ($purchasableIds as $purchasableId) {
                    if (!isset($priceByPurchasableId[$purchasableId])) {
                        continue;
                    }

                    $price = null;
                    if ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE) {
                        $price = $priceByPurchasableId[$purchasableId]['price'];
                    } else if ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE) {
                        $price = $priceByPurchasableId[$purchasableId]['promotionalPrice'] ?? $priceByPurchasableId[$purchasableId]['price'];
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

        $transaction = Craft::$app->getDb()->beginTransaction();
        // Truncate the catalog pricing table
        Craft::$app->getDb()->createCommand()->truncateTable(Table::CATALOG_PRICING)->execute();

        if (!empty($catalogPricing)) {
            // Batch through `$catalogPricing` and insert into the catalog pricing table
            $count = 1;
            $startTime = microtime(true);
            $total = Craft::$app->getFormatter()->asDecimal(count($catalogPricing), 0);
            foreach (array_chunk($catalogPricing, 2000) as $catalogPricingChunk) {
                $fromCount = Craft::$app->getFormatter()->asDecimal($count, 0);
                $toCount = ($count + 1999) > count($catalogPricing) ? $total : Craft::$app->getFormatter()->asDecimal($count + count($catalogPricingChunk) - 1, 0);
                if ($showConsoleOutput) {
                    Console::stdout(PHP_EOL . sprintf('Generating prices rows %s to %s of %s... ', $fromCount, $toCount, $total));
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
                $count += 2000;
                if ($showConsoleOutput) {
                    Console::stdout('done!');
                }
            }

            $executionLength = microtime(true) - $startTime;
            if ($showConsoleOutput) {
                Console::stdout(PHP_EOL . 'Generated ' . $total . ' prices in ' . round($executionLength) . ' seconds' . PHP_EOL);
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
    public function createCatalogPricingQuery(?int $userId = null, ?int $storeId = null, ?bool $isPromotionalPrice = null): Query
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
