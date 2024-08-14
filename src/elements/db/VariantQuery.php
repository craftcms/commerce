<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\elements\VariantCollection;
use craft\commerce\Plugin;
use craft\commerce\records\Sale;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Expression;

/**
 * VariantQuery represents a SELECT SQL statement for variants in a way that is independent of DBMS.
 *
 *
 * @template TKey of array-key
 * @template TElement of Variant
 *
 * @method Variant[]|array all($db = null)
 * @method Variant|array|null one($db = null)
 * @method Variant|array|null nth(int $n, Connection $db = null)
 * @method self siteId($value)
 * @method self status(array|string|null $value)
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
class VariantQuery extends PurchasableQuery
{
    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['elements_owners.sortOrder' => SORT_ASC];

    /**
     * @var bool Whether to only return variants that the user has permission to edit.
     */
    public bool $editable = false;

    /**
     * @var bool|null
     */
    public ?bool $hasSales = null;

    /**
     * @var mixed only return variants that match the resulting product query.
     */
    public mixed $hasProduct = null;

    /**
     * @var bool|null
     */
    public ?bool $isDefault = null;


    /**
     * @var mixed The primary owner element ID(s) that the resulting entries must belong to.
     * @used-by primaryOwner()
     * @used-by primaryOwnerId()
     * @since 5.0.0
     */
    public mixed $primaryOwnerId = null;

    /**
     * @var mixed|null
     * @used-by owner()
     * @used-by ownerId()
     * @since 5.0.0
     */
    public mixed $ownerId = null;

    /**
     * @var mixed
     */
    public mixed $typeId = null;

    /**
     * @var mixed
     */
    public mixed $minQty = null;

    /**
     * @var mixed
     */
    public mixed $maxQty = null;

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
        switch ($name) {
            case 'product':
                $this->product($value);
                break;
            case 'productId':
                // Added due to the removal of the `$productId` property
                $this->ownerId($value);
                break;
            case 'owner':
                $this->owner($value);
                break;
            case 'primaryOwner':
                $this->primaryOwner($value);
                break;
            default:
                parent::__set($name, $value);
        }
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
    public function product(mixed $value): VariantQuery
    {
        if ($value instanceof Product) {
            $this->ownerId = [$value->id];
        } else {
            $this->ownerId = $value;
        }
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ owner.
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
    public function owner(mixed $value): VariantQuery
    {
        if ($value instanceof ElementInterface) {
            $this->ownerId = [$value->id];
        } else {
            $this->ownerId = $value;
        }
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ primary owner.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[ElementInterface|ElementInterface]] object | for a product represented by the object.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function primaryOwner(mixed $value): VariantQuery
    {
        if ($value instanceof ElementInterface) {
            $this->primaryOwnerId = [$value->id];
        } else {
            $this->primaryOwnerId = $value;
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
    public function productId(mixed $value): VariantQuery
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ primary owners’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a primary owner with an ID of 1.
     * | `[1, 2]` | for primary owner with an ID of 1 or 2.
     * | `['not', 1, 2]` | for primary owner not with an ID of 1 or 2.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function primaryOwnerId(mixed $value): VariantQuery
    {
        $this->primaryOwnerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ owners’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for an owner with an ID of 1.
     * | `[1, 2]` | for owner with an ID of 1 or 2.
     * | `['not', 1, 2]` | for owner not with an ID of 1 or 2.
     *
     * @param mixed $value
     * @return static self reference
     */
    public function ownerId(mixed $value): VariantQuery
    {
        $this->ownerId = $value;
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
    public function typeId(mixed $value): VariantQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isDefault()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch default variants
     * ${elements-var} = {element-class}::find()
     *     ->isDefault()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isDefault(?bool $value = true): VariantQuery
    {
        $this->isDefault = $value;
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
     * @param bool|null $value
     * @return static self reference
     */
    public function hasSales(?bool $value = true): VariantQuery
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
     * @param mixed $value The property value
     * @return static self reference
     */
    public function hasProduct(mixed $value = []): VariantQuery
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
    public function minQty(mixed $value): VariantQuery
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
    public function maxQty(mixed $value): VariantQuery
    {
        $this->maxQty = $value;
        return $this;
    }

    /**
     * @param Connection|null $db
     * @return VariantCollection
     * @phpstan-ignore-next-line
     */
    public function collect(?Connection $db = null): VariantCollection
    {
        /** @phpstan-ignore-next-line */
        return VariantCollection::make(parent::collect($db));
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        try {
            $this->primaryOwnerId = $this->_normalizeOwnerId($this->primaryOwnerId);
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid primaryOwnerId param value');
        }

        try {
            $this->ownerId = $this->_normalizeOwnerId($this->ownerId);
        } catch (InvalidArgumentException) {
            throw new InvalidConfigException('Invalid ownerId param value');
        }

        $this->joinElementTable('commerce_variants');

        $this->query->select([
            'commerce_variants.id',
            'commerce_variants.primaryOwnerId',
            'isDefault' => new Expression('CASE WHEN [[commerce_variants]].[[id]] = [[commerce_products]].[[defaultVariantId]] THEN TRUE ELSE FALSE END'),
            'commerce_products_elements_sites.slug as productSlug',
            'commerce_producttypes.handle as productTypeHandle',
        ]);

        // Join in the elements_owners table
        $ownersCondition = [
            'and',
            '[[elements_owners.elementId]] = [[elements.id]]',
            $this->ownerId ? ['elements_owners.ownerId' => $this->ownerId] : '[[elements_owners.ownerId]] = [[commerce_variants.primaryOwnerId]]',
        ];

        $this->query
            ->addSelect([
                'elements_owners.ownerId',
                'elements_owners.sortOrder',
            ])
            ->innerJoin(['elements_owners' => CraftTable::ELEMENTS_OWNERS], $ownersCondition);
        $this->subQuery->innerJoin(['elements_owners' => CraftTable::ELEMENTS_OWNERS], $ownersCondition);

        if ($this->primaryOwnerId) {
            $this->subQuery->andWhere(['commerce_variants.primaryOwnerId' => $this->primaryOwnerId]);
        }

        $this->query->leftJoin(Table::PRODUCTS . ' commerce_products', '[[elements_owners.ownerId]] = [[commerce_products.id]]');
        $this->query->leftJoin(Table::PRODUCTTYPES . ' commerce_producttypes', '[[commerce_products.typeId]] = [[commerce_producttypes.id]]');
        $this->query->leftJoin(CraftTable::ELEMENTS_SITES . ' commerce_products_elements_sites', '[[elements_owners.ownerId]] = [[commerce_products_elements_sites.elementId]] and [[commerce_products_elements_sites.siteId]] =  [[elements_sites.siteId]]');

        $this->subQuery->leftJoin(Table::PRODUCTS . ' commerce_products', '[[elements_owners.ownerId]] = [[commerce_products.id]]');
        $this->subQuery->leftJoin(Table::PRODUCTTYPES . ' commerce_producttypes', '[[commerce_products.typeId]] = [[commerce_producttypes.id]]');

        if (isset($this->typeId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.typeId', $this->typeId));
        }

        if (isset($this->productId)) {
            $this->subQuery->andWhere(['commerce_variants.primaryOwnerId' => $this->productId]);
        }

        if (isset($this->isDefault)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('isDefault', $this->isDefault, false));
        }

        if (isset($this->minQty)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.minQty', $this->minQty));
        }

        if (isset($this->maxQty)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.maxQty', $this->maxQty));
        }

        // If width, height or length is specified in the query we should only be looking for products that
        // have a type which supports dimensions
        if ($this->width !== false || $this->height !== false || $this->length !== false || $this->weight !== false) {
            $this->subQuery->andWhere(Db::parseParam('commerce_producttypes.hasDimensions', 1));
        }

        if (isset($this->hasSales)) {
            if (!Plugin::getInstance()->getSales()->canUseSales()) {
                Craft::$app->getDeprecator()->log('VariantQuery::hasSales', 'The `hasSales` parameter and Sales have been deprecated, use Pricing Rules instead.');
                return false;
            }

            $now = new DateTime();
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
                    ],
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
                $hasSalesVariantConditions = [];
                $hasSalesProductConditions = [];

                if (empty($allProductsAndCategoriesSales)) {
                    $purchasableRestrictedSales = ArrayHelper::whereMultiple($activeSales, ['allPurchasables' => 0]);
                    $categoryRestrictedSales = ArrayHelper::whereMultiple($activeSales, ['allCategories' => 0]);

                    $purchasableRestrictedQuery = (new Query())
                        ->select('purchasableId')
                        ->from(Table::SALE_PURCHASABLES . ' sp')
                        ->where([
                            'saleId' => ArrayHelper::getColumn($purchasableRestrictedSales, 'id'),
                        ]);
                    $hasSalesVariantConditions[] = ['commerce_variants.id' => $purchasableRestrictedQuery];

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
                            ],
                        ]);

                        // Source relationships
                        if (!empty($sourceSales)) {
                            $sourceQueryProduct = (new Query())
                                ->select('rel.sourceId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.targetId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.sourceId]]')
                                ->leftJoin(CraftTable::ELEMENTS_SITES . ' es', '[[es.elementId]] = [[sc.categoryId]]')
                                ->where(['saleId' => ArrayHelper::getColumn($sourceSales, 'id')])
                                ->andWhere(['elements.type' => Product::class])
                                ->andWhere(Db::parseParam('es.siteId', $this->siteId))
                                ->andWhere(['es.enabled' => true]);
                            $hasSalesProductConditions[] = ['commerce_variants.primaryOwnerId' => $sourceQueryProduct];

                            $sourceQueryVariant = (new Query())
                                ->select('rel.sourceId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.targetId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.sourceId]]')
                                ->leftJoin(CraftTable::ELEMENTS_SITES . ' es', '[[es.elementId]] = [[sc.categoryId]]')
                                ->where(['saleId' => ArrayHelper::getColumn($sourceSales, 'id')])
                                ->andWhere(['elements.type' => Variant::class])
                                ->andWhere(Db::parseParam('es.siteId', $this->siteId))
                                ->andWhere(['es.enabled' => true]);
                            $hasSalesVariantConditions[] = ['commerce_variants.id' => $sourceQueryVariant];
                        }

                        // Target relationships
                        if (!empty($targetSales)) {
                            $targetQueryProduct = (new Query())
                                ->select('rel.targetId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.sourceId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.targetId]]')
                                ->leftJoin(CraftTable::ELEMENTS_SITES . ' es', '[[es.elementId]] = [[sc.categoryId]]')
                                ->where(['saleId' => ArrayHelper::getColumn($targetSales, 'id')])
                                ->andWhere(['elements.type' => Product::class])
                                ->andWhere(Db::parseParam('es.siteId', $this->siteId))
                                ->andWhere(['es.enabled' => true]);
                            $hasSalesProductConditions[] = ['commerce_variants.primaryOwnerId' => $targetQueryProduct];

                            $targetQueryVariant = (new Query())
                                ->select('rel.targetId')
                                ->from(Table::SALE_CATEGORIES . ' sc')
                                ->leftJoin(CraftTable::RELATIONS . ' rel', '[[rel.sourceId]] = [[sc.categoryId]]')
                                ->leftJoin(CraftTable::ELEMENTS . ' elements', '[[elements.id]] = [[rel.targetId]]')
                                ->leftJoin(CraftTable::ELEMENTS_SITES . ' es', '[[es.elementId]] = [[sc.categoryId]]')
                                ->where(['saleId' => ArrayHelper::getColumn($targetSales, 'id')])
                                ->andWhere(['elements.type' => Variant::class])
                                ->andWhere(Db::parseParam('es.siteId', $this->siteId))
                                ->andWhere(['es.enabled' => true]);
                            $hasSalesVariantConditions[] = ['commerce_variants.id' => $targetQueryVariant];
                        }
                    }
                }
            }

            $hasSalesCondition = ['or'];
            if (!empty($hasSalesVariantConditions)) {
                $hasSalesCondition[] = array_merge(['or'], $hasSalesVariantConditions);
            }

            if (!empty($hasSalesProductConditions)) {
                $hasSalesCondition[] = array_merge(['or'], $hasSalesProductConditions);
            }

            if ($this->hasSales) {
                $this->subQuery->andWhere(['purchasables_stores.promotable' => true]);
                $this->subQuery->andWhere($hasSalesCondition);
            } else {
                $this->subQuery->andWhere(['not', $hasSalesCondition]);
            }
        }

        $this->_applyHasProductParam();

        return parent::beforePrepare();
    }

    /**
     * Normalizes the primaryOwnerId param to an array of IDs or null
     *
     * @param mixed $value
     * @return int[]|null
     * @throws InvalidArgumentException
     */
    private function _normalizeOwnerId(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return [$value];
        }
        if (!is_array($value) || !ArrayHelper::isNumeric($value)) {
            throw new InvalidArgumentException();
        }
        return $value;
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
            $productQuery = Product::find();
            $productQuery = Craft::configure($productQuery, $this->hasProduct);
        } else {
            return;
        }

        $productQuery->limit = null;
        $productQuery->select('commerce_products.id');

        // Remove any blank product IDs (if any)
        $productQuery->andWhere(['not', ['commerce_products.id' => null]]);

        $this->subQuery->andWhere(['commerce_variants.primaryOwnerId' => $productQuery]);
    }

    /**
     * @inheritdoc
     * @since 3.5.0
     */
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->ownerId) {
            foreach ($this->ownerId as $ownerId) {
                $tags[] = "product:$ownerId";
            }
        }

        return $tags;
    }
}
