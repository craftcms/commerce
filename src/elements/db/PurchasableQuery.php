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
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
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
abstract class PurchasableQuery extends ElementQuery
{
    protected array $defaultOrderBy = ['commerce_purchasables.sku' => SORT_ASC];

    /**
     * @var bool|null Whether the purchasable is available for purchase
     */
    public ?bool $availableForPurchase = null;

    /**
     * @var mixed the SKU of the variant
     */
    public mixed $sku = null;

    /**
     * @var mixed|null
     */
    public mixed $price = null;

    /**
     * @var mixed|null
     */
    public mixed $promotionalPrice = null;

    /**
     * @var bool|null
     */
    public bool|null $hasPromotionalPrice = null;

    /**
     * @var bool|null
     */
    public bool|null $isOnPromotion = null;

    /**
     * @var mixed|null
     */
    public mixed $salePrice = null;

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
     * @var mixed
     */
    public mixed $stock = null;

    /**
     * @var bool|null
     */
    public ?bool $hasStock = null;

    /**
     * @var bool|null
     */
    public ?bool $hasUnlimitedStock = null;

    /**
     * @var mixed The shipping category ID(s) that the resulting products must have.
     */
    public mixed $shippingCategoryId = null;

    /**
     * @var mixed The tax category ID(s) that the resulting products must have.
     */
    public mixed $taxCategoryId = null;

    /**
     * @var int|false|null
     */
    public int|false|null $forCustomer = null;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'shippingCategory':
                $this->shippingCategory($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Narrows the query results to only purchasables that are available for purchase.
     *
     * ---
     *
     * ```twig
     * {# Fetch purchasables that are available for purchase #}
     * {% set {elements-var} = {twig-method}
     *   .availableForPurchase()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch purchasables that are available for purchase
     * ${elements-var} = {element-class}::find()
     *     ->availableForPurchase()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function availableForPurchase(?bool $value = true): static
    {
        $this->availableForPurchase = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ SKUs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | with a SKU of `foo`.
     * | `'foo*'` | with a SKU that begins with `foo`.
     * | `'*foo'` | with a SKU that ends with `foo`.
     * | `'*foo*'` | with a SKU that contains `foo`.
     * | `'not *foo*'` | with a SKU that doesn’t contain `foo`.
     * | `['*foo*', '*bar*'` | with a SKU that contains `foo` or `bar`.
     * | `['not', '*foo*', '*bar*']` | with a SKU that doesn’t contain `foo` or `bar`.
     *
     * ---
     *
     * ```twig
     * {# Get the requested {element} SKU from the URL #}
     * {% set requestedSlug = craft.app.request.getSegment(3) %}
     *
     * {# Fetch the {element} with that slug #}
     * {% set {element-var} = {twig-method}
     *   .sku(requestedSlug|literal)
     *   .one() %}
     * ```
     *
     * ```php
     * // Get the requested {element} SKU from the URL
     * $requestedSlug = \Craft::$app->request->getSegment(3);
     *
     * // Fetch the {element} with that slug
     * ${element-var} = {php-method}
     *     ->sku(\craft\helpers\Db::escapeParam($requestedSlug))
     *     ->one();
     * ```
     *
     * @param mixed $value
     * @return static self reference
     */
    public function sku(mixed $value): static
    {
        $this->sku = $value;
        return $this;
    }

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
    public mixed $inventoryTracked = null;

    /**
     * Narrows the query results based on the variants’ stock.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `0` | with no stock.
     * | `'>= 5'` | with a stock of at least 5.
     * | `'< 10'` | with a stock of less than 10.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function stock(mixed $value): static
    {
        $this->stock = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants that have stock.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with stock.
     * | `false` | with no stock.
     *
     * @param bool|null $value
     * @return static self reference
     */
    public function hasStock(?bool $value = true): static
    {
        $this->hasStock = $value;
        return $this;
    }

    /**
     * Narrows the pricing query results to only prices related for the specified customer.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with user ID of `1`.
     * | `false` | with prices for guest customers.
     * | `null` | with prices for current user scenario.
     *
     * @param int|false|null $value
     * @return static self reference
     * @noinspection PhpUnused
     */
    public function forCustomer(int|false|null $value = null): static
    {
        $this->forCustomer = $value;
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
     * Narrows the query results based on the purchasable’s price.
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
     * Narrows the query results to only variants that have been set to not track stock.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with inventory tracked not checked.
     * | `false` | with inventory tracked checked.
     *
     * @param bool|null $value
     * @return static self reference
     * @since 3.3.4
     * @noinspection PhpUnused
     * @deprecated in 5.0.0. Use `inventoryTracked` instead.
     */
    public function hasUnlimitedStock(?bool $value = true): static
    {
        $this->inventoryTracked = !$value; // reverse for backward compatibility
        return $this;
    }

    /**
     * Narrows the query results to only variants that have been set to track stock.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with inventory tracked checked.
     * | `false` | with inventory tracked  not checked.
     *
     * @param bool|null $value
     * @return static self reference
     * @since 3.3.4
     * @noinspection PhpUnused
     */
    public function inventoryTracked(?bool $value = true): static
    {
        $this->inventoryTracked = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the purchasable’s promotional price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a promotional price of 100.
     * | `'>= 100'` | with a promotional price of at least 100.
     * | `'< 100'` | with a promotional price of less than 100.
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
     * Narrows the query results based on the purchasable’s sale price.
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
    public function salePrice(mixed $value): static
    {
        $this->salePrice = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ shipping categories, per the shipping categories’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a shipping category with an ID of 1.
     * | `'not 1'` | not of a shipping category with an ID of 1.
     * | `[1, 2]` | of a shipping category with an ID of 1 or 2.
     * | `['not', 1, 2]` | not of a shipping category with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the shipping category with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .shippingCategoryId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the shipping category with an ID of 1
     * ${elements-var} = {php-method}
     *     ->shippingCategoryId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function shippingCategoryId(mixed $value): static
    {
        $this->shippingCategoryId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ shipping category.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | of a shipping category with a handle of `foo`.
     * | `'not foo'` | not of a shipping category with a handle of `foo`.
     * | `['foo', 'bar']` | of a shipping category with a handle of `foo` or `bar`.
     * | `['not', 'foo', 'bar']` | not of a shipping category with a handle of `foo` or `bar`.
     * | an [[ShippingCategory|ShippingCategory]] object | of a shipping category represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a Foo shipping category #}
     * {% set {elements-var} = {twig-method}
     *   .shippingCategory('foo')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a Foo shipping category
     * ${elements-var} = {php-method}
     *     ->shippingCategory('foo')
     *     ->all();
     * ```
     *
     * @param ShippingCategory|string|null|array<string> $value The property value
     * @return static self reference
     */
    public function shippingCategory(mixed $value): static
    {
        if ($value instanceof ShippingCategory) {
            $this->shippingCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->shippingCategoryId = (new Query())
                ->from(['shippingcategories' => Table::SHIPPINGCATEGORIES])
                ->where(['shippingcategories.id' => new Expression('[[purchasables_stores.shippingCategoryId]]')])
                ->andWhere(Db::parseParam('handle', $value));
        } else {
            $this->shippingCategoryId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the products’ tax categories, per the tax categories’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a tax category with an ID of 1.
     * | `'not 1'` | not of a tax category with an ID of 1.
     * | `[1, 2]` | of a tax category with an ID of 1 or 2.
     * | `['not', 1, 2]` | not of a tax category with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the tax category with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .taxCategoryId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the tax category with an ID of 1
     * ${elements-var} = {php-method}
     *     ->taxCategoryId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function taxCategoryId(mixed $value): static
    {
        $this->taxCategoryId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ tax category.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | of a tax category with a handle of `foo`.
     * | `'not foo'` | not of a tax category with a handle of `foo`.
     * | `['foo', 'bar']` | of a tax category with a handle of `foo` or `bar`.
     * | `['not', 'foo', 'bar']` | not of a tax category with a handle of `foo` or `bar`.
     * | an [[ShippingCategory|ShippingCategory]] object | of a tax category represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a Foo tax category #}
     * {% set {elements-var} = {twig-method}
     *   .taxCategory('foo')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a Foo tax category
     * ${elements-var} = {php-method}
     *     ->taxCategory('foo')
     *     ->all();
     * ```
     *
     * @param TaxCategory|string|null|array<string> $value The property value
     * @return static self reference
     */
    public function taxCategory(mixed $value): static
    {
        if ($value instanceof TaxCategory) {
            $this->taxCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->taxCategoryId = (new Query())
                ->from(['taxcategories' => Table::TAXCATEGORIES])
                ->where(['taxcategories.id' => new Expression('[[commerce_purchasables.taxCategoryId]]')])
                ->andWhere(Db::parseParam('handle', $value));
        } else {
            $this->taxCategoryId = null;
        }

        return $this;
    }

    /**
     * Return only purchasables with an active promotional price via catalog pricing rules (or which *do not* have an active promotional price).
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with a promotional price.
     * | `false` | without a promotional price.
     * | `null` | without taking into consideration the relationship between their price and promotional price.
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function hasPromotionalPrice(bool|null $value = true): static
    {
        $this->hasPromotionalPrice = $value;
        return $this;
    }

    /**
     * Return only purchasables with a promotional price less than their price. This respects catalog pricing, and matches the return value from [[Purchasable::getIsOnPromotion()]].
     *
     * This method does not compare catalog prices against *base* prices, so even if a price is reduced by a rule, the purchasable will only be returned when the promotional price is less. In the same way, passing `false` *can* return purchasables with prices lower than their base price!
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | with a promotional price less than its price.
     * | `false` | with no promotional price, or a promotional price that is equal to its price.
     * | `null` | without considering the relationship between their price and promotional price.
     *
     * Combine with [[forCustomer()]] to check whether a specific user (or a guest) would be eligible for promotional pricing.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that are on promotion: #}
     * {% set {elements-var} = {twig-method}
     *   .isOnPromotion(true)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that are on promotion:
     * ${elements-var} = {php-method}
     *     ->isOnPromotion(true)
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isOnPromotion(bool|null $value = true): static
    {
        $this->isOnPromotion = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function afterPrepare(): bool
    {
        // Store dependent related joins to the sub query need to be done after the `elements_sites` is joined in the base `ElementQuery` class.
        $customerId = $this->forCustomer;
        if ($customerId === null) {
            $customerId = Craft::$app->getUser()->getIdentity()?->id;
        } elseif ($customerId === false) {
            $customerId = null;
        }

        $catalogPricesQuery = Plugin::getInstance()
            ->getCatalogPricing()
            ->createCatalogPricesQuery(userId: $customerId)
            ->addSelect(['cp.purchasableId', 'cp.storeId']);

        $this->subQuery->leftJoin(['sitestores' => Table::SITESTORES], '[[elements_sites.siteId]] = [[sitestores.siteId]]');
        $this->subQuery->leftJoin(['purchasables_stores' => Table::PURCHASABLES_STORES], '[[purchasables_stores.storeId]] = [[sitestores.storeId]] AND [[purchasables_stores.purchasableId]] = [[commerce_purchasables.id]]');

        $this->subQuery->leftJoin(['catalogprices' => $catalogPricesQuery], '[[catalogprices.purchasableId]] = [[commerce_purchasables.id]] AND [[catalogprices.storeId]] = [[sitestores.storeId]]');
        $this->subQuery->leftJoin(['inventoryitems' => Table::INVENTORYITEMS], '[[inventoryitems.purchasableId]] = [[commerce_purchasables.id]]');

        return parent::afterPrepare();
    }

    /**
     * @inheritdoc
     */
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
            'purchasables_stores.availableForPurchase',
            'purchasables_stores.basePrice',
            'purchasables_stores.basePromotionalPrice',
            'purchasables_stores.freeShipping',
            'purchasables_stores.maxQty',
            'purchasables_stores.minQty',
            'purchasables_stores.inventoryTracked',
            'purchasables_stores.promotable',
            'purchasables_stores.shippingCategoryId',
            'subquery.price',
            'subquery.promotionalPrice as promotionalPrice',
            'subquery.salePrice as salePrice',
            'inventoryitems.id as inventoryItemId',
        ]);

        $this->query->leftJoin(Table::SITESTORES . ' sitestores', '[[elements_sites.siteId]] = [[sitestores.siteId]]');
        $this->query->leftJoin(Table::PURCHASABLES_STORES . ' purchasables_stores', '[[purchasables_stores.storeId]] = [[sitestores.storeId]] AND [[purchasables_stores.purchasableId]] = [[commerce_purchasables.id]]');
        $this->query->leftJoin(['inventoryitems' => Table::INVENTORYITEMS], '[[inventoryitems.purchasableId]] = [[commerce_purchasables.id]]');

        $this->subQuery->addSelect([
            'catalogprices.price',
            'catalogprices.promotionalPrice',
            'catalogprices.salePrice',
        ]);

        if (isset($this->sku)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.sku', $this->sku));
        }

        // We don't join the inventory levels table, and rely on the caches store available total.
        if (isset($this->stock)) {
            $this->subQuery->andWhere(Db::parseParam('purchasables_stores.stock', $this->stock));
        }

        if (isset($this->inventoryTracked)) {
            $this->subQuery->andWhere(Db::parseParam('purchasables_stores.inventoryTracked', $this->inventoryTracked));
        }

        if (isset($this->availableForPurchase)) {
            $this->subQuery->andWhere(['purchasables_stores.availableForPurchase' => $this->availableForPurchase]);
        }

        if (isset($this->sku)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_purchasables.sku', $this->sku));
        }

        if (isset($this->price)) {
            $this->subQuery->andWhere(Db::parseNumericParam('catalogprices.price', $this->price));
        }

        if (isset($this->promotionalPrice)) {
            $this->subQuery->andWhere(Db::parseNumericParam('catalogprices.promotionalPrice', $this->promotionalPrice));
        }

        if (isset($this->hasPromotionalPrice)) {
            if ($this->hasPromotionalPrice) {
                $this->subQuery->andWhere(new Expression('catalogprices.price != catalogprices.promotionalPrice'));
            } else {
                // Commerce normalizes these when selecting/aggregating, so the values will actually be the same when a promotional price doesn't exist. This means it's not technically possible to distinguish between an *unset* promotional price and a promotional price that ended up being the same as the regular price. It’s also ambiguous when a pricing rule sets a `promotionalPrice` based on the original `price`!
                $this->subQuery->andWhere(new Expression('catalogprices.price = catalogprices.promotionalPrice'));
            }
        }

        if (isset($this->isOnPromotion)) {
            if ($this->isOnPromotion) {
                $this->subQuery->andWhere(new Expression('catalogprices.price > catalogprices.promotionalPrice'));
            } else {
                // Effective price is less than or equal to effective promotional price (`price` should never be less than `promotionalPrice` based on how they’re aggregated in the pricing subquery—but semantically, this matches what we’re trying to do):
                $this->subQuery->andWhere(new Expression('catalogprices.price <= catalogprices.promotionalPrice'));
            }
        }


        if (isset($this->salePrice)) {
            $this->subQuery->andWhere(Db::parseNumericParam('catalogprices.salePrice' , $this->salePrice));
        }

        if (isset($this->shippingCategoryId)) {
            if ($this->shippingCategoryId instanceof Query) {
                $shippingCategoryWhere = ['exists', $this->shippingCategoryId];
            } else {
                $shippingCategoryWhere = Db::parseParam('purchasables_stores.shippingCategoryId', $this->shippingCategoryId);
            }

            $this->subQuery->andWhere($shippingCategoryWhere);
        }

        if (isset($this->taxCategoryId)) {
            if ($this->taxCategoryId instanceof Query) {
                $taxCategoryWhere = ['exists', $this->taxCategoryId];
            } else {
                $taxCategoryWhere = Db::parseParam('commerce_purchasables.taxCategoryId', $this->taxCategoryId);
            }

            $this->subQuery->andWhere($taxCategoryWhere);
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

        if (isset($this->hasStock)) {
            if ($this->hasStock) {
                $this->subQuery->andWhere([
                    'or',
                    ['purchasables_stores.inventoryTracked' => false],
                    [
                        'and',
                        ['not', ['purchasables_stores.inventoryTracked' => false]],
                        ['>', 'purchasables_stores.stock', 0],
                    ],
                ]);
            } else {
                $this->subQuery->andWhere([
                    'and',
                    ['not', ['purchasables_stores.inventoryTracked' => false]],
                    ['<', 'purchasables_stores.stock', 1],
                ]);
            }
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    public function populate($rows): array
    {
        foreach ($rows as &$row) {
            unset($row['salePrice']);
        }

        return parent::populate($rows);
    }
}
