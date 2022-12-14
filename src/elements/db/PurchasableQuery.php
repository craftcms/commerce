<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\commerce\models\PurchasableStore;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;

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
     * @var mixed|null
     */
    public mixed $promotionalPrice = null;

    /**
     * @var mixed
     */
    public mixed $width = false;

    /**
     * @var mixed
     */
    public mixed $height = false;

    /**
     * @var mixed
     */
    public mixed $length = false;

    /**
     * @var mixed
     */
    public mixed $weight = false;

    /**
     * @var bool|null
     */
    public ?bool $hasUnlimitedStock = null;

    /**
     * Narrows the query results to only variants that have been set to unlimited stock.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with unlimited stock checked.
     * | `false` | with unlimited stock not checked.
     *
     * @param bool|null $value
     * @return static self reference
     * @noinspection PhpUnused
     */
    public function hasUnlimitedStock(?bool $value = true): static
    {
        $this->hasUnlimitedStock = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ width dimension.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a width of 100.
     * | `'>= 100'` | with a width of at least 100.
     * | `'< 100'` | with a width of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function width(mixed $value): static
    {
        $this->width = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ height dimension.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a height of 100.
     * | `'>= 100'` | with a height of at least 100.
     * | `'< 100'` | with a height of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function height(mixed $value): static
    {
        $this->height = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ length dimension.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a length of 100.
     * | `'>= 100'` | with a length of at least 100.
     * | `'< 100'` | with a length of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function length(mixed $value): static
    {
        $this->length = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ weight dimension.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a weight of 100.
     * | `'>= 100'` | with a weight of at least 100.
     * | `'< 100'` | with a weight of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function weight(mixed $value): static
    {
        $this->weight = $value;
        return $this;
    }

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
    public function price(mixed $value): static
    {
        $this->price = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the purchasables’ promotional price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a sale price of 100.
     * | `'>= 100'` | with a sale price of at least 100.
     * | `'< 100'` | with a sale price of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function promotionalPrice(mixed $value): static
    {
        $this->promotionalPrice = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the purchasables’ store.
     * Setting `null` will narrow the query based on the request's store.
     * Setting `false` will cause the join of prices for all stores.
     *
     * @param mixed|null $store
     * @return static
     * @throws \yii\base\InvalidConfigException
     */
    public function store(mixed $store = null): static
    {
        if ($store instanceof Store) {
            $this->store = $store;
        } elseif (is_numeric($store)) {
            $this->store = Plugin::getInstance()->getStores()->getStoreById($store);
        } elseif (is_string($store)) {
            $this->store = Plugin::getInstance()->getStores()->getStoreByHandle($store);
        } else {
            $this->store = $store;
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function populate($rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $purchasableStoresAttributes = array_merge(Craft::createObject(PurchasableStore::class)->safeAttributes(), ['id']);
        foreach ($rows as &$row) {
            $purchasableStores = [];
            Plugin::getInstance()->getStores()->getAllStores()->each(function($store) use (&$row, $purchasableStoresAttributes, &$purchasableStores) {
                if (!isset($purchasableStores[$store->id])) {
                    $purchasableStores[$store->id] = [];
                }

                foreach ($purchasableStoresAttributes as $attribute) {
                    $purchasableStores[$store->id][$attribute] = $row[$store->handle . '_' . $attribute] ?? null;
                    unset($row[$store->handle . '_' . $attribute]);
                }
            });
            $row['purchasableStores'] = $purchasableStores;
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
        $this->query->addSelect([
            'commerce_purchasables.sku',
            'commerce_purchasables.width',
            'commerce_purchasables.height',
            'commerce_purchasables.length',
            'commerce_purchasables.weight',
            'commerce_purchasables.taxCategoryId',
            'commerce_purchasables.shippingCategoryId',
        ]);

        // Get all purchasable stores data in one query by joining the table multiple times.
        foreach (Plugin::getInstance()->getStores()->getAllStores()->all() as $store) {
            $storeTableNameAlias = $store->handle . '_commerce_purchasables_stores';
            $storeTableName = Table::PURCHASABLES_STORES . ' ' . $storeTableNameAlias;
            $selectColumns = collect(array_merge(Craft::createObject(PurchasableStore::class)->safeAttributes(), ['id']))->map(function ($column) use ($storeTableNameAlias, $store) {
                return $storeTableNameAlias . '.' . $column . ' as ' . $store->handle . '_' . $column;
            })->all();
            $this->query->addSelect($selectColumns);
            $onStatement = sprintf('[[%s]] = [[%s]] AND [[%s]] = %s', $storeTableNameAlias . '.purchasableId', 'commerce_purchasables.id', $storeTableNameAlias . '.storeId', $store->id);
            $this->query->leftJoin($storeTableName, $onStatement);
        }

        if (isset($this->price) && $this->store !== false) {
            $priceQuery = (new Query())
                ->select(['purchasableId'])
                ->from([
                    'ruleprice' => Plugin::getInstance()
                        ->getCatalogPricing()
                        ->createCatalogPricingQuery(null, $this->store->id)
                        ->addSelect(['cp.purchasableId']),
                ])
                ->where(Db::parseNumericParam('price', $this->price));

            $this->subQuery->andWhere(['commerce_purchasables.id' => $priceQuery]);
        }

        if (isset($this->promotionalPrice) && $this->store !== false) {
            $promotionalPriceQuery = (new Query())
                ->select(['purchasableId'])
                ->from([
                    'promotionalpricequery' => Plugin::getInstance()
                        ->getCatalogPricing()
                        ->createCatalogPricingQuery(null, $this->store->id, true)
                        ->addSelect(['cp.purchasableId']),
                ])
                ->where(Db::parseNumericParam('price', $this->promotionalPrice));

            $this->subQuery->andWhere(['commerce_purchasables.id' => $promotionalPriceQuery]);
        }

        if ($this->width !== false) {
            if ($this->width === null) {
                $this->subQuery->andWhere(['commerce_purchasables.width' => $this->width]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.width', $this->width));
            }
        }

        if ($this->height !== false) {
            if ($this->height === null) {
                $this->subQuery->andWhere(['commerce_purchasables.height' => $this->height]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.height', $this->height));
            }
        }

        if ($this->length !== false) {
            if ($this->length === null) {
                $this->subQuery->andWhere(['commerce_purchasables.length' => $this->length]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.length', $this->length));
            }
        }

        if ($this->weight !== false) {
            if ($this->weight === null) {
                $this->subQuery->andWhere(['commerce_purchasables.weight' => $this->weight]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.weight', $this->weight));
            }
        }

        if (isset($this->hasUnlimitedStock)) {
            $this->subQuery->andWhere([
                $this->store->handle . '_commerce_purchasables_stores.hasUnlimitedStock' => $this->hasUnlimitedStock,
            ]);
        }

        return parent::beforePrepare();
    }
}
