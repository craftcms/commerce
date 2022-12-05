<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;
use yii\db\Expression;

/**
 * PurchasableQuery represents a SELECT SQL statement for purchasables in a way that is independent of DBMS.
 *
 * @method Purchasable[]|array all($db = null)
 * @method Purchasable|array|null one($db = null)
 * @method Purchasable|array|null nth(int $n, Connection $db = null)
 * @since 5.0.0
 */
class PurchasableQuery extends ElementQuery
{
    protected array $defaultOrderBy = ['commerce_purchasables.sku' => SORT_ASC];

    /**
     * @var mixed|null
     */
    public mixed $store = null;

    /**
     * @var mixed|null
     */
    public mixed $price = null;

    /**
     * Narrows the query results based on the purchasables’ price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a price of 100.
     * | `'>= 100'` | with a price of at least 100.
     * | `'< 100'` | with a price of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function price(mixed $value): PurchasableQuery
    {
        $this->price = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the purchasables’ store.
     * Setting `null` will narrow the query based on the request's store.
     * Setting `false` will cause the join of prices for all stores.
     *
     * @param mixed|null $store
     * @return PurchasableQuery
     * @throws \yii\base\InvalidConfigException
     */
    public function store(mixed $store = null): PurchasableQuery
    {
        if ($store instanceof Store) {
            $this->store = $store;
        } else if (is_numeric($store)) {
            $this->store = Plugin::getInstance()->getStores()->getStoreById($store);
        } else if (is_string($store)) {
            $this->store = Plugin::getInstance()->getStores()->getStoreByHandle($store);
        } else {
            $this->store = $store;
        }
        return $this;
    }

    public function populate($rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $storeHandles = Plugin::getInstance()->getStores()->getAllStores()->map(fn($store) => $store->handle)->all();
        foreach ($rows as &$row) {
            $row['basePrices'] = [];
            $row['baseSalePrices'] = [];
            foreach ($storeHandles as $storeHandle) {
                $row['basePrices'][$storeHandle] = $row[$storeHandle . '_basePrice'] ?? null;
                $row['baseSalePrices'][$storeHandle] = $row[$storeHandle . '_baseSalePrice'] ?? null;
                unset($row[$storeHandle . '_basePrice'], $row[$storeHandle . '_baseSalePrice']);
            }
        }

        return parent::populate($rows);
    }

    protected function beforePrepare(): bool
    {
        // If a store hasn't been specified use the store for the current request.
        if ($this->store === null) {
            $this->store = Plugin::getInstance()->getStores()->getCurrentStore();
        }

        $this->joinElementTable('commerce_purchasables');
        $storeIdsByHandle = Plugin::getInstance()->getStores()->getAllStores()->keyBy('handle')->map(fn($store) => $store->id)->all();
        $select = [];
        $joins = [];
        foreach ($storeIdsByHandle as $storeHandle => $storeId) {
            if ($this->store !== false && $this->store->id !== $storeId) {
                continue;
            }

            $tableName = "commerce_pricing_price_$storeHandle";
            $saleTableName = "commerce_pricing_sale_price_$storeHandle";
            $select[] = "$tableName.price as {$storeHandle}_basePrice";
            $select[] = "$saleTableName.price as {$storeHandle}_baseSalePrice";
            $joins[] = [
                'table' => Table::CATALOG_PRICING . " $tableName",
                'on' => "[[commerce_purchasables.id]] = [[$tableName.purchasableId]] AND [[$tableName.storeId]] =:{$storeHandle}PriceStoreId AND [[$tableName.catalogPricingRuleId]] is NULL  AND [[$tableName.isPromotionalPrice]] = 0",
                'params' => [":{$storeHandle}PriceStoreId" => $storeId],
            ];
            $joins[] = [
                'table' => Table::CATALOG_PRICING . " $saleTableName",
                'on' => "[[commerce_purchasables.id]] = [[$saleTableName.purchasableId]] AND [[$saleTableName.storeId]] =:{$storeHandle}SalePriceStoreId AND [[$saleTableName.catalogPricingRuleId]] is NULL AND [[$saleTableName.isPromotionalPrice]] = 1",
                'params' => [":{$storeHandle}SalePriceStoreId" => $storeId],
            ];
        }

        if (!empty($select)) {
            $this->query->addSelect($select);
        }

        foreach ($joins as $join) {
            $this->query->leftJoin($join['table'], $join['on'], $join['params']);
        }

        if (isset($this->price) && $this->store !== false) {
            $priceQuery = (new Query())
                ->select(['purchasableId'])
                ->from([
                    'ruleprice' => Plugin::getInstance()
                        ->getCatalogPricing()
                        ->createCatalogPricingQuery(null, $this->store->id)
                        ->addSelect(['cp.purchasableId'])
                ])
                ->where(Db::parseNumericParam('price', $this->price));

            $this->subQuery->andWhere(['commerce_purchasables.id' => $priceQuery]);
        }

        return parent::beforePrepare();
    }
}