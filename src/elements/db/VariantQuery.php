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
use craft\commerce\Plugin;
use craft\commerce\records\Sale;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use yii\db\Connection;

/**
 * VariantQuery represents a SELECT SQL statement for variants in a way that is independent of DBMS.
 *
 * @method Variant[]|array all($db = null)
 * @method Variant|array|null one($db = null)
 * @method Variant|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * @var string the SKU of the variant
     */
    public $sku;

    /**
     * @var bool Whether to only return variants that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var
     */
    public $productId;

    /**
     * @var
     */
    public $typeId;

    /**
     * @var
     */
    public $isDefault;

    /**
     * @var
     */
    public $stock;

    /**
     * @var
     */
    public $hasStock;

    /**
     * @var
     */
    public $price;

    /**
     * @var
     */
    public $hasSales;

    /**
     * @var ProductQuery|array only return variants that match the resulting product query.
     */
    public $hasProduct;

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['commerce_variants.sortOrder' => SORT_ASC];

    /**
     * @var
     */
    public $minQty;

    /**
     * @var
     */
    public $maxQty;


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
    public function sku($value)
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
    public function product($value)
    {
        $this->product = $value;
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
    public function productId($value)
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
    public function typeId($value)
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
    public function isDefault(bool $value = true)
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
    public function stock($value)
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
    public function price($value)
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
    public function hasStock(bool $value = true)
    {
        $this->hasStock = $value;
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
    public function hasSales(bool $value = true)
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
    public function hasProduct($value)
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
    public function minQty($value)
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
    public function maxQty($value)
    {
        $this->maxQty = $value;
        return $this;
    }



    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
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

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.typeId', $this->typeId));
        }

        if ($this->sku) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.sku', $this->sku));
        }

        if ($this->product) {
            if ($this->product instanceof Product) {
                $this->productId = $this->product->id;
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.productId', $this->product));
            }
        }

        if ($this->productId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.productId', $this->productId));
        }

        if ($this->price) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.price', $this->price));
        }

        if ($this->isDefault) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.isDefault', $this->isDefault));
        }

        if ($this->minQty) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.minQty', $this->minQty));
        }

        if ($this->maxQty) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.maxQty', $this->maxQty));
        }

        if ($this->stock) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.stock', $this->stock));
        }

        if (null !== $this->hasStock && (bool)$this->hasStock === true) {
            $hasStockCondition = ['or', '(commerce_variants.stock > 0 AND commerce_variants.hasUnlimitedStock != 1)', 'commerce_variants.hasUnlimitedStock = 1'];
            $this->subQuery->andWhere($hasStockCondition);
        }

        if (null !== $this->hasStock && (bool)$this->hasStock === false) {
            $hasStockCondition = ['and', 'commerce_variants.stock < 1', 'commerce_variants.hasUnlimitedStock != 1'];
            $this->subQuery->andWhere($hasStockCondition);
        }

        if (null !== $this->hasSales) {
            // We can't just clone the query as it may be modifying the select statement etc (i.e in the product query‘s hasVariant param)
            // But we want to use the same conditions so that we improve performance over searching all variants
            $query = Variant::find();
            foreach ($this->criteriaAttributes() as $attribute) {
                $query->$attribute = $this->$attribute;
            }

            $query->andWhere(['commerce_products.promotable' => 1]);
            $query->hasSales = null;
            $query->limit = null;
            $variantIds = $query->ids();

            $productIds = Product::find()
                ->andWhere(['promotable' => 1])
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
                ->where(['[[enabled]]' => 1])
                ->andWhere([
                    'or',
                    ['or', ['<>', '[[dateTo]]', null], ['>=', '[[dateTo]]', Db::prepareDateForDb($now)]],
                    ['or', ['<>', '[[dateFrom]]', null], ['<=', '[[dateFrom]]', Db::prepareDateForDb($now)]],
                    ['[[dateFrom]]' => null, '[[dateTo]]' => null],
                ])
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
                            ->where(['[[sales.id]]' => $activeSaleIds])
                            ->andWhere(['in', 'userGroupId', $userGroupIds])
                            ->column();

                        foreach ($activeSales as $activeSale) {
                            if ($activeSale['allGroups'] == 0 && !in_array($activeSale['id'], $userGroupSalesIds)) {
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
                        ->where(['in', 'saleId', ArrayHelper::getColumn($purchasableRestrictedSales, 'id')])
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
                                ->where(['in', 'saleId', ArrayHelper::getColumn($sourceSales, 'id')])
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
                                ->where(['in', 'saleId', ArrayHelper::getColumn($targetSales, 'id')])
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

            $includeExcludePhrase = $this->hasSales ? 'in' : 'not in';
            $this->subQuery->andWhere([$includeExcludePhrase, 'commerce_variants.id', $variantIds]);
            $this->subQuery->orWhere([$includeExcludePhrase, 'commerce_variants.productId', $productIds]);
        }

        $this->_applyHasProductParam();

        return parent::beforePrepare();
    }

    /**
     * Applies the hasVariant query condition
     */
    private function _applyHasProductParam()
    {
        if ($this->hasProduct) {
            if ($this->hasProduct instanceof ProductQuery) {
                $productQuery = $this->hasProduct;
            } else {
                $query = Product::find();
                $productQuery = Craft::configure($query, $this->hasProduct);
            }

            $productQuery->limit = null;
            $productQuery->select('commerce_products.id');
            $productIds = $productQuery->column();

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);

            $this->subQuery->andWhere(['in', 'commerce_products.id', $productIds]);
        }
    }
}
