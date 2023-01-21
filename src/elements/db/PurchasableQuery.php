<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
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

    protected function beforePrepare(): bool
    {

        $this->joinElementTable('commerce_purchasables');
        $this->query->addSelect([
            'commerce_purchasables.sku',
            'commerce_purchasables.width',
            'commerce_purchasables.height',
            'commerce_purchasables.length',
            'commerce_purchasables.weight',
            'commerce_purchasables.taxCategoryId',
            'commerce_purchasables.shippingCategoryId',
            'purchasables_stores.basePrice',
            'purchasables_stores.basePromotionalPrice',
            'purchasables_stores.promotable',
            'purchasables_stores.availableForPurchase',
            'purchasables_stores.freeShipping',
            'purchasables_stores.hasUnlimitedStock',
            'purchasables_stores.stock',
            'purchasables_stores.minQty',
            'purchasables_stores.maxQty',
        ]);

        $this->query->leftJoin(Table::SITESTORES . ' sitestores', '[[elements_sites.siteId]] = [[sitestores.siteId]]');
        $this->query->leftJoin(Table::PURCHASABLES_STORES . ' purchasables_stores', '[[purchasables_stores.storeId]] = [[sitestores.storeId]] AND [[purchasables_stores.purchasableId]] = [[commerce_purchasables.id]]');

        if (isset($this->price)) {
            $priceQuery = (new Query())
                ->select(['purchasableId'])
                ->from([
                    'ruleprice' => Plugin::getInstance()
                        ->getCatalogPricing()
                        ->createCatalogPricingQuery(null, 'purchasables_stores.storeId')
                        ->addSelect(['cp.purchasableId']),
                ])
                ->where(Db::parseNumericParam('price', $this->price));

            $this->subQuery->andWhere(['commerce_purchasables.id' => $priceQuery]);
        }

        if (isset($this->promotionalPrice)) {
            $promotionalPriceQuery = (new Query())
                ->select(['purchasableId'])
                ->from([
                    'promotionalpricequery' => Plugin::getInstance()
                        ->getCatalogPricing()
                        ->createCatalogPricingQuery(null, 'purchasables_stores.storeId', true)
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
                'purchasables_stores.hasUnlimitedStock' => $this->hasUnlimitedStock,
            ]);
        }

        return parent::beforePrepare();
    }
}
