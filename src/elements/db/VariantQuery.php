<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\base\Element;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\records\Sale;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use yii\db\Connection;
use yii\db\Schema;

/**
 * VariantQuery represents a SELECT SQL statement for variants in a way that is independent of DBMS.
 *
 * @method Variant[]|array all($db = null)
 * @method Variant|array|null one($db = null)
 * @method Variant|array|null nth(int $n, Connection $db = null)
 * @property-write Product $product
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @doc-path products-variants.md
 * @prefix-doc-params
 * @replace {element} variant
 * @replace {elements} variants
 * @replace {twig-method} craft.variants()
 * @replace {myElement} myVariant
 * @replace {element-class} \craft\commerce\elements\Variant
 * @supports-site-params
 * @supports-status-param
 * @supports-title-param
 */
class VariantQuery extends ElementQuery
{

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['commerce_variants.sortOrder' => SORT_ASC];

    /**
     * @var bool Whether to only return variants that the user has permission to edit.
     */
    public bool $editable = false;

    /**
     * @var bool
     */
    public bool $hasStock;

    /**
     * @var bool
     */
    public bool $hasSales;

    /**
     * @var ProductQuery|array only return variants that match the resulting product query.
     */
    public $hasProduct;

    /**
     * @var bool
     */
    public bool $isDefault;

    /**
     * @var mixed
     */
    public $price;

    /**
     * @var mixed
     */
    public $productId;

    /**
     * @var mixed the SKU of the variant
     */
    public $sku;

    /**
     * @var mixed
     */
    public $stock;

    /**
     * @var mixed
     */
    public $typeId;

    /**
     * @var bool
     * @since 3.3.4
     */
    public bool $hasUnlimitedStock;

    /**
     * @var mixed
     */
    public $minQty;

    /**
     * @var mixed
     */
    public $maxQty;

    /**
     * @var mixed
     * @since 3.2.0
     */
    public $width = false;

    /**
     * @var mixed
     * @since 3.2.0
     */
    public $height = false;

    /**
     * @var mixed
     * @since 3.2.0
     */
    public $length = false;

    /**
     * @var mixed
     * @since 3.2.0
     */
    public $weight = false;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Element::STATUS_ENABLED;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($name == 'product') {
            $this->product($value);
        } else {
            parent::__set($name, $value);
        }
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
     *     .sku(requestedSlug|literal)
     *     .one() %}
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
    public function sku($value): VariantQuery
    {
        $this->sku = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ product.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[Product|Product]] object | for a product represented by the object.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function product($value): VariantQuery
    {
        if ($value instanceof Product) {
            $this->productId = [$value->id];
        } else {
            $this->productId = $value;
        }
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ products’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a product with an ID of 1.
     * | `[1, 2]` | for product with an ID of 1 or 2.
     * | `['not', 1, 2]` | for product not with an ID of 1 or 2.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function productId($value): VariantQuery
    {
        $this->productId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ product types, per their IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a product of a type with an ID of 1.
     * | `[1, 2]` | for product of a type with an ID of 1 or 2.
     * | `['not', 1, 2]` | for product of a type not with an ID of 1 or 2.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function typeId($value): VariantQuery
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Narrows the query results to only default variants.
     *
     * ---
     *
     * ```twig
     * {# Fetch default variants #}
     * {% set {elements-var} = {twig-function}
     *     .isDefault()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch default variants
     * ${elements-var} = {element-class}::find()
     *     ->isDefault()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isDefault(bool $value = true): VariantQuery
    {
        $this->isDefault = $value;
        return $this;
    }

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
    public function stock($value): VariantQuery
    {
        $this->stock = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ price.
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
    public function price($value): VariantQuery
    {
        $this->price = $value;
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
     * @param bool $value
     * @return static self reference
     */
    public function hasStock(bool $value = true): VariantQuery
    {
        $this->hasStock = $value;
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
     * @param bool $value
     * @return static self reference
     * @since 3.3.4
     * @noinspection PhpUnused
     */
    public function hasUnlimitedStock(bool $value = true): VariantQuery
    {
        $this->hasUnlimitedStock = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants that are on sale.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `true` | on sale
     * | `false` | not on sale
     *
     * @param bool $value
     * @return static self reference
     */
    public function hasSales(bool $value = true): VariantQuery
    {
        $this->hasSales = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants for certain products.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[ProductQuery|ProductQuery]] object | for products that match the query.
     *
     * @param ProductQuery|array $value The property value
     * @return static self reference
     */
    public function hasProduct($value): VariantQuery
    {
        $this->hasProduct = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ min quantity.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a minQty of 100.
     * | `'>= 100'` | with a minQty of at least 100.
     * | `'< 100'` | with a minQty of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function minQty($value): VariantQuery
    {
        $this->minQty = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ max quantity.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with a maxQty of 100.
     * | `'>= 100'` | with a maxQty of at least 100.
     * | `'< 100'` | with a maxQty of less than 100.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function maxQty($value): VariantQuery
    {
        $this->maxQty = $value;
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
    public function width($value): VariantQuery
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
    public function height($value): VariantQuery
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
    public function length($value): VariantQuery
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
    public function weight($value): VariantQuery
    {
        $this->weight = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->_normalizeProductId();

        // See if 'productId' was invalid
        if ($this->productId === []) {
            return false;
        }

        $this->joinElementTable('commerce_variants');

        $this->query->select([
            'commerce_variants.id',
            'commerce_variants.productId',
            'commerce_variants.isDefault',
            'commerce_variants.sku',
            'commerce_variants.price',
            'commerce_variants.sortOrder',
            'commerce_variants.width',
            'commerce_variants.height',
            'commerce_variants.length',
            'commerce_variants.weight',
            'commerce_variants.stock',
            'commerce_variants.hasUnlimitedStock',
            'commerce_variants.minQty',
            'commerce_variants.maxQty'
        ]);

        $this->subQuery->leftJoin(Table::PRODUCTS . ' commerce_products', '[[commerce_variants.productId]] = [[commerce_products.id]]');
        $this->subQuery->leftJoin(Table::PRODUCTTYPES . ' commerce_producttypes', '[[commerce_products.typeId]] = [[commerce_producttypes.id]]');

        if (isset($this->typeId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.typeId', $this->typeId));
        }

        if (isset($this->sku)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.sku', $this->sku));
        }

        if (isset($this->productId)) {
            $this->subQuery->andWhere(['commerce_variants.productId' => $this->productId]);
        }

        if (isset($this->price)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.price', $this->price));
        }

        if (isset($this->isDefault) && $this->isDefault !== null) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.isDefault', $this->isDefault, '=', false, Schema::TYPE_BOOLEAN));
        }

        if (isset($this->minQty)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.minQty', $this->minQty));
        }

        if (isset($this->maxQty)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.maxQty', $this->maxQty));
        }

        if (isset($this->stock)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.stock', $this->stock));
        }

        if ($this->width !== false) {
            if ($this->width === null) {
                $this->subQuery->andWhere(['commerce_variants.width' => $this->width]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.width', $this->width));
            }
        }

        if ($this->height !== false) {
            if ($this->height === null) {
                $this->subQuery->andWhere(['commerce_variants.height' => $this->height]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.height', $this->height));
            }
        }

        if ($this->length !== false) {
            if ($this->length === null) {
                $this->subQuery->andWhere(['commerce_variants.length' => $this->length]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.length', $this->length));
            }
        }

        if ($this->weight !== false) {
            if ($this->weight === null) {
                $this->subQuery->andWhere(['commerce_variants.weight' => $this->weight]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.weight', $this->weight));
            }
        }

        // If width, height or length is specified in the query we should only be looking for products that
        // have a type which supports dimensions
        if ($this->width !== false || $this->height !== false || $this->length !== false || $this->weight !== false) {
            $this->subQuery->andWhere(Db::parseParam('commerce_producttypes.hasDimensions', 1));
        }

        if (isset($this->hasUnlimitedStock)) {
            $this->subQuery->andWhere([
                'commerce_variants.hasUnlimitedStock' => $this->hasUnlimitedStock
            ]);
        }

        if (isset($this->hasStock)) {
            if ($this->hasStock) {
                $this->subQuery->andWhere([
                    'or',
                    ['commerce_variants.hasUnlimitedStock' => true],
                    [
                        'and',
                        ['not', ['commerce_variants.hasUnlimitedStock' => true]],
                        ['>', 'commerce_variants.stock', 0],
                    ],
                ]);
            } else {
                $this->subQuery->andWhere([
                    'and',
                    ['not', ['commerce_variants.hasUnlimitedStock' => true]],
                    ['<', 'commerce_variants.stock', 1],
                ]);
            }
        }

        if (isset($this->hasSales)) {
            // We can't just clone the query as it may be modifying the select statement etc (i.e in the product query‘s hasVariant param)
            // But we want to use the same conditions so that we improve performance over searching all variants
            $query = Variant::find();
            foreach ($this->criteriaAttributes() as $attribute) {
                $query->$attribute = $this->$attribute;
            }

            $query->andWhere(['commerce_products.promotable' => true]);
            unset($query->hasSales);
            $query->limit = null;
            $variantIds = $query->ids();

            $productIds = Product::find()
                ->andWhere(['promotable' => true])
                ->limit(null)
                ->ids();

            $now = new \DateTime();
            $activeSales = (new Query())->select([
                'sales.id',
                'sales.allGroups',
                'sales.allPurchasables',
                'sales.allCategories',
                'sales.categoryRelationshipType',
            ])
                ->from(Table::SALES . ' sales')
                ->where([
                    'or',
                    // Only a from date
                    [
                        'and',
                        ['dateTo' => null],
                        ['not', ['dateFrom' => null]],
                        ['<=', 'dateFrom', Db::prepareDateForDb($now)],
                    ],
                    // Only a to date
                    [
                        'and',
                        ['dateFrom' => null],
                        ['not', ['dateTo' => null]],
                        ['>=', 'dateTo', Db::prepareDateForDb($now)],
                    ],
                    // no dates
                    [
                        'dateFrom' => null,
                        'dateTo' => null,
                    ],
                    // to and from dates
                    [
                        'and',
                        ['not', ['dateFrom' => null]],
                        ['not', ['dateTo' => null]],
                        ['<=', 'dateFrom', Db::prepareDateForDb($now)],
                        ['>=', 'dateTo', Db::prepareDateForDb($now)],
                    ]
                ])
                ->andWhere(['enabled' => true])
                ->orderBy('sortOrder asc')
                ->all();

            $allVariantsMatch = false;
            foreach ($activeSales as $activeSale) {
                if ($activeSale['allGroups'] == 1 && $activeSale['allPurchasables'] == 1 && $activeSale['allCategories'] == 1) {
                    $allVariantsMatch = true;
                    break;
                }
            }

            if (!$allVariantsMatch) {
                $activeSaleIds = ArrayHelper::getColumn($activeSales, 'id');

                // Only force user group restriction on site requests
                if (Craft::$app->getRequest()->isSiteRequest) {
                    $user = Craft::$app->getUser()->getIdentity();
                    $userGroupIds = [];

                    if ($user) {
                        $userGroupIds = ArrayHelper::getColumn($user->getGroups(), 'id');
                    }

                    // If the user doesn't belong to any groups, remove sales that
                    // restrict by user group as these would never match
                    if (empty($userGroupIds)) {
                        foreach ($activeSales as $activeSale) {
                            if ($activeSale['allGroups'] == 0) {
                                ArrayHelper::removeValue($activeSaleIds, $activeSale['id']);
                                break;
                            }
                        }
                    } else {
                        // Exclude any sales that have a user group restriction that the current user is not part of
                        $userGroupSalesIds = (new Query())
                            ->select('sales.id')
                            ->from(Table::SALES . ' sales')
                            ->leftJoin(Table::SALE_USERGROUPS . ' su', '[[su.saleId]] = [[sales.id]]')
                            ->where([
                                'sales.id' => $activeSaleIds,
                                'userGroupId' => $userGroupIds,
                            ])
                            ->column();

                        foreach ($activeSales as $activeSale) {
                            if ($activeSale['allGroups'] == 0 && !in_array($activeSale['id'], $userGroupSalesIds, false)) {
                                ArrayHelper::removeValue($activeSaleIds, $activeSale['id']);
                            }
                        }
                    }
                }

                $activeSales = ArrayHelper::whereMultiple($activeSales, ['id' => $activeSaleIds]);

                // Check to see if we have any sales that match all products and categories
                // so we can skip extra processing if needed
                $allProductsAndCategoriesSales = ArrayHelper::whereMultiple($activeSales, ['allPurchasables' => 1, 'allCategories' => 1]);

                if (empty($allProductsAndCategoriesSales)) {
                    $purchasableRestrictedSales = ArrayHelper::whereMultiple($activeSales, ['allPurchasables' => 0]);
                    $categoryRestrictedSales = ArrayHelper::whereMultiple($activeSales, ['allCategories' => 0]);

                    $purchasableRestrictedIds = (new Query())
                        ->select('purchasableId')
                        ->from(Table::SALE_PURCHASABLES . ' sp')
                        ->where([
                            'saleId' => ArrayHelper::getColumn($purchasableRestrictedSales, 'id'),
                        ])
                        ->column();

                    $categoryRestrictedVariantIds = [];
                    $categoryRestrictedProductIds = [];
                    if (!empty($categoryRestrictedSales)) {
                        $sourceSales = ArrayHelper::whereMultiple($categoryRestrictedSales, [
                            'categoryRelationshipType' => [
                                Sale::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                                Sale::CATEGORY_RELATIONSHIP_TYPE_BOTH,
                            ],
                        ]);
                        $targetSales = ArrayHelper::whereMultiple($categoryRestrictedSales, [
                            'categoryRelationshipType' => [
                                Sale::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                                Sale::CATEGORY_RELATIONSHIP_TYPE_BOTH,
                            ]
                        ]);

                        // Source relationships
                        $sourceVariantIds = [];
                        $sourceProductIds = [];
                        if (!empty($sourceSales)) {
                            $sourceRows = (new Query())
                                ->select('elements.type, rel.sourceId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.targetId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.sourceId]]')
                                ->where([
                                    'saleId' => ArrayHelper::getColumn($sourceSales, 'id'),
                                ])
                                ->all();

                            $sourceProductIds = ArrayHelper::getColumn($sourceRows, function($row) {
                                if ($row['type'] == Product::class) {
                                    return $row['sourceId'];
                                }
                            });
                            $sourceVariantIds = ArrayHelper::getColumn($sourceRows, function($row) {
                                if ($row['type'] == Variant::class) {
                                    return $row['sourceId'];
                                }
                            });

                            $sourceProductIds = array_filter($sourceProductIds);
                            $sourceVariantIds = array_filter($sourceVariantIds);
                        }

                        // Target relationships
                        $targetVariantIds = [];
                        $targetProductIds = [];
                        if (!empty($targetSales)) {
                            $targetRows = (new Query())
                                ->select('elements.type, rel.targetId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.sourceId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.targetId]]')
                                ->where([
                                    'saleId' => ArrayHelper::getColumn($targetSales, 'id'),
                                ])
                                ->all();

                            $targetProductIds = ArrayHelper::getColumn($targetRows, function($row) {
                                if ($row['type'] == Product::class) {
                                    return $row['targetId'];
                                }
                            });
                            $targetVariantIds = ArrayHelper::getColumn($targetRows, function($row) {
                                if ($row['type'] == Variant::class) {
                                    return $row['targetId'];
                                }
                            });

                            $targetProductIds = array_filter($targetProductIds);
                            $targetVariantIds = array_filter($targetVariantIds);
                        }

                        $categoryRestrictedVariantIds = array_merge($sourceVariantIds, $targetVariantIds);
                        $categoryRestrictedProductIds = array_merge($sourceProductIds, $targetProductIds);
                    }

                    $variantIds = array_unique(array_merge($purchasableRestrictedIds, $categoryRestrictedVariantIds));
                    $productIds = $categoryRestrictedProductIds;
                }
            }

            $hasSalesCondition = [
                'or',
                ['commerce_variants.id' => $variantIds],
                ['commerce_variants.productId' => $productIds],
            ];

            if ($this->hasSales) {
                $this->subQuery->andWhere($hasSalesCondition);
            } else {
                $this->subQuery->andWhere(['not', $hasSalesCondition]);
            }
        }

        $this->_applyHasProductParam();

        return parent::beforePrepare();
    }

    /**
     * Normalizes the productId param to an array of IDs or null
     */
    private function _normalizeProductId(): void
    {
        if (empty($this->productId)) {
            $this->productId = null;
        } else if (is_numeric($this->productId)) {
            $this->productId = [$this->productId];
        } else if (!is_array($this->productId) || !ArrayHelper::isNumeric($this->productId)) {
            $this->productId = (new Query())
                ->select(['id'])
                ->from([Table::PRODUCTS])
                ->where(Db::parseParam('id', $this->productId))
                ->column();
        }
    }

    /**
     * Applies the hasProduct query condition
     */
    private function _applyHasProductParam(): void
    {
        if (!isset($this->hasProduct)) {
            return;
        }

        if ($this->hasProduct instanceof ProductQuery) {
            $productQuery = $this->hasProduct;
        } elseif (is_array($this->hasProduct)) {
            $query = Product::find();
            $productQuery = Craft::configure($query, $this->hasProduct);
        } else {
            return;
        }

        $productQuery->limit = null;
        $productQuery->select('commerce_products.id');
        $productIds = $productQuery->column();

        // Remove any blank product IDs (if any)
        $productIds = array_filter($productIds);
        $this->subQuery->andWhere(['commerce_variants.productId' => $productIds]);
    }

    /**
     * @inheritdoc
     * @since 3.5.0
     */
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->productId) {
            foreach ($this->productId as $productId) {
                $tags[] = "product:$productId";
            }
        }

        return $tags;
    }
}
