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
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\Db;
use DateTime;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Catalog Pricing service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricing extends Component
{
    /**
     * @param array|null $purchasables
     * @param array|null $catalogPricingRules
     * @param bool $showConsoleOutput
     * @return void
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function generateCatalogPrices(?array $purchasables = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false): void
    {
        if ($purchasables === null) {
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

        // Generate all standard catalog pricing rules
        $catalogPricing = [];
        $priceByPurchasableId = [];
        foreach ($purchasables as $purchasable) {
            /** @var Purchasable $purchasable */
            $id = $purchasable->getId();
            $price = $purchasable->getPrice();
            $catalogPricing[] = [
                $id, // purchasableId
                $price, // price
                1, // storeId
                0, // isPromotionalPrice
                null, // catalogPricingRuleId
                null, // dateFrom
                null, // dateTo
            ];
            $priceByPurchasableId[$id] = $price;
        }

        $catalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules();

        foreach ($catalogPricingRules as $catalogPricingRule) {
            // If `getPurchasableIds()` is `null` this means all purchasables
            $purchasableIds = $catalogPricingRule->getPurchasableIds() ?? ArrayHelper::getColumn($purchasables, 'id');

            if (empty($purchasableIds)) {
                continue;
            }

            foreach ($purchasableIds as $purchasableId) {
                $catalogPricing[] = [
                    $purchasableId, // purchasableId
                    $catalogPricingRule->getRulePriceFromPrice($priceByPurchasableId[$purchasableId]), // price
                    1, // storeId
                    $catalogPricingRule->isPromotionalPrice, // isPromotionalPrice
                    $catalogPricingRule->id, // catalogPricingRuleId
                    $catalogPricingRule->dateFrom ? Db::prepareDateForDb($catalogPricingRule->dateFrom) : null, // dateFrom
                    $catalogPricingRule->dateTo ? Db::prepareDateForDb($catalogPricingRule->dateTo) : null, // dateTo
                ];
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
                Console::stdout('done!');
            }

            $executionLength = microtime(true) - $startTime;
            if ($showConsoleOutput) {
                Console::stdout(PHP_EOL . 'Generated ' . $total . ' prices in ' . round($executionLength) . ' seconds' . PHP_EOL);
            }
        }

        $transaction?->commit();
    }

    /**
     * Return the catalog price for a purchasable.
     *
     * @param int $purchasableId
     * @param int|null $storeId
     * @param int|null $userId
     * @return float|null
     * @throws InvalidConfigException
     */
    public function getCatalogPrice(int $purchasableId, ?int $storeId = null, ?int $userId = null): ?float
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStore()->getStore()->id;

        $catalogPricingQuery = (new Query())
            ->select(['price'])
            ->from([Table::CATALOG_PRICING . ' cp'])
            ->leftJoin(Table::CATALOG_PRICING_RULES_USERS . ' cpru', '[[cpru.catalogPricingRuleId]] = [[cp.catalogPricingRuleId]]')
            ->orderBy(['price' => SORT_ASC])
            ->where(['purchasableId' => $purchasableId])
            ->andWhere(['storeId' => $storeId])
            ->andWhere(['or', ['dateFrom' => null], ['<=', 'dateFrom', Db::prepareDateForDb(new DateTime())]])
            ->andWhere(['or', ['dateTo' => null], ['>=', 'dateTo', Db::prepareDateForDb(new DateTime())]])
            ->andWhere(['[[cpru.userId]]' => ['or', $userId, null]]);

        return $catalogPricingQuery->scalar();
    }
}
