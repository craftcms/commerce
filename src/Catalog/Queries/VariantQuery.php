<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Queries;

use Closure;
use craft\commerce\elements\VariantCollection;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Concerns\QueriesNestedElements;
use CraftCms\Cms\Element\Queries\Contracts\NestedElementQueryInterface;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Query;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\ProductQuery as ProductQueryHelper;
use CraftCms\Commerce\Promotion\Records\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

use Tpetry\QueryExpressions\Language\Alias;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;

/**
 * @extends PurchasableQuery<Variant>
 */
class VariantQuery extends PurchasableQuery implements NestedElementQueryInterface
{
    /**
     * The `QueriesNestedElements` concern is used for its param API (`owner()`, `ownerId()`,
     * `primaryOwner()`, `primaryOwnerId()`, `field()`, `fieldId()`, `allowOwnerDrafts()`,
     * `allowOwnerRevisions()`) and its cache tags. Its own `initQueriesNestedElements()` and
     * `fieldLayouts()` are replaced below: those two methods route through private helpers whose
     * signatures are hard-typed to core's `AddressQuery|ContentBlockQuery|EntryQuery` union, so
     * calling them with a commerce query would throw a `TypeError`. Variants also need a bespoke
     * `elements_owners` join anyway (they're not stored in a field, so there's no `fieldId`).
     */
    use QueriesNestedElements {
        cacheTags as nestedTraitCacheTags;
    }

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = ['elements_owners.sortOrder' => SORT_ASC];

    /**
     * Whether to only return variants that the user has permission to view.
     */
    public ?bool $editable = null;

    /**
     * Whether to only return variants that the user has permission to save.
     */
    public ?bool $savable = null;

    public ?bool $hasSales = null;

    /**
     * Only return variants that match the resulting product query.
     */
    public mixed $hasProduct = null;

    public ?bool $isDefault = null;

    /**
     * The status the owner product must have.
     *
     * @var array<int, string>|string|null
     */
    public array|string|null $productStatus = null;

    public mixed $typeId = null;

    public mixed $minQty = null;

    public mixed $maxQty = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = [Element::STATUS_ENABLED];
        }

        parent::__construct(Variant::class, $config);

        $this->query->join(new Alias(Table::VARIANTS, 'commerce_variants'), 'commerce_variants.id', '=', 'elements.id');

        $this->query->addSelect([
            'commerce_variants.primaryOwnerId',
        ]);

        $this->beforeQuery(function(self $query) {
            $query->joinOwners();

            if ($query->primaryOwnerId) {
                $query->whereIn('commerce_variants.primaryOwnerId', $query->primaryOwnerId);
            }

            if (isset($query->typeId)) {
                $query->whereParam('commerce_products.typeId', $query->typeId);
            }

            if (isset($query->isDefault)) {
                if ($query->isDefault) {
                    $query->whereColumn('commerce_variants.id', '=', 'commerce_products.defaultVariantId');
                } else {
                    $query->where(function(Builder $subQuery) {
                        $subQuery->whereNull('commerce_products.defaultVariantId')
                            ->orWhereColumn('commerce_variants.id', '!=', 'commerce_products.defaultVariantId');
                    });
                }
            }

            // `minQty`/`maxQty` live on the purchasable's per-store row, not on `commerce_variants`
            // (the legacy query filtered `commerce_variants.minQty`/`maxQty`, which no longer exist
            // as columns — those params raised a SQL error).
            if (isset($query->minQty)) {
                $query->whereParam('purchasables_stores.minQty', $query->minQty);
            }

            if (isset($query->maxQty)) {
                $query->whereParam('purchasables_stores.maxQty', $query->maxQty);
            }

            // If width, height or length is specified in the query we should only be looking for products that
            // have a type which supports dimensions
            if ($query->width !== false || $query->height !== false || $query->length !== false || $query->weight !== false) {
                $query->whereParam('commerce_producttypes.hasDimensions', true);
            }

            $query->applyProductStatusParam();
            $query->applyHasSalesParam();
            $query->applyHasProductParam();
            $query->applyPermissionParam($query->editable, 'commerce-viewProductType');
            $query->applyPermissionParam($query->savable, 'commerce-saveProductType');
        });
    }

    public function getFieldIdColumn(): string
    {
        // Variants aren't stored in a custom field, so there is no `fieldId` column. The primary
        // owner column is returned here for parity with the legacy query.
        return 'commerce_variants.primaryOwnerId';
    }

    public function getPrimaryOwnerIdColumn(): string
    {
        return 'commerce_variants.primaryOwnerId';
    }

    /**
     * Replaces {@see QueriesNestedElements::initQueriesNestedElements()} — see the trait import
     * note on this class. The `elements_owners` join (and the product/product type joins that
     * hang off it) are set up by {@see joinOwners()} instead.
     */
    protected function initQueriesNestedElements(): void
    {
    }

    /** @return Collection<int, FieldLayout> */
    #[Override]
    protected function fieldLayouts(): Collection
    {
        // Bypasses QueriesNestedElements::fieldLayouts(), which normalizes the `fieldId` param via
        // a core-only-typed helper. Variants get their field layouts from their product types,
        // which are registered against this element type.
        return parent::fieldLayouts();
    }

    /**
     * Narrows the query results based on the variants’ product.
     */
    public function product(mixed $value): static
    {
        if ($value instanceof ElementInterface) {
            $this->ownerId = [$value->id];
        } else {
            $this->ownerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the variants’ owner.
     *
     * Widened from {@see QueriesNestedElements::owner()} to also accept owner IDs, matching the
     * legacy query's behavior.
     */
    public function owner(mixed $value): static
    {
        /** @phpstan-ignore-next-line instanceof.alwaysTrue (widened to also accept owner IDs - PHPStan appears to be using NestedElementQueryInterface::owner()'s stricter ElementInterface param type here, not this override's mixed) */
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
     * Widened from {@see QueriesNestedElements::primaryOwner()} to also accept owner IDs, matching
     * the legacy query's behavior.
     */
    public function primaryOwner(mixed $value): static
    {
        /** @phpstan-ignore-next-line instanceof.alwaysTrue (widened to also accept owner IDs - PHPStan appears to be using NestedElementQueryInterface::primaryOwner()'s stricter ElementInterface param type here, not this override's mixed) */
        if ($value instanceof ElementInterface) {
            $this->primaryOwnerId = [$value->id];
        } else {
            $this->primaryOwnerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the variants’ products’ IDs.
     */
    public function productId(mixed $value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ products’ statuses.
     *
     * @param string|string[]|null $value
     */
    public function productStatus(array|string|null $value): static
    {
        $this->productStatus = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ product types, per their IDs.
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Narrows the query results to only default variants.
     */
    public function isDefault(?bool $value = true): static
    {
        $this->isDefault = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants that are on sale.
     */
    public function hasSales(?bool $value = true): static
    {
        $this->hasSales = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants for certain products.
     *
     * @param ProductQuery|array<string, mixed> $value
     */
    public function hasProduct(mixed $value = []): static
    {
        $this->hasProduct = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ min quantity.
     */
    public function minQty(mixed $value): static
    {
        $this->minQty = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ max quantity.
     */
    public function maxQty(mixed $value): static
    {
        $this->maxQty = $value;
        return $this;
    }

    public function editable(?bool $value = true): static
    {
        $this->editable = $value;
        return $this;
    }

    public function savable(?bool $value = true): static
    {
        $this->savable = $value;
        return $this;
    }

    #[Override]
    public function collect(): VariantCollection
    {
        return VariantCollection::make(parent::collect()->all());
    }

    /**
     * Joins the `elements_owners` table, plus the owner product, its product type, and the owner
     * product's site settings — all of which the variant's selected columns and params rely on.
     */
    private function joinOwners(): void
    {
        $this->primaryOwnerId = $this->normalizeOwnerIdParam($this->primaryOwnerId, 'primaryOwnerId');
        $this->ownerId = $this->normalizeOwnerIdParam($this->ownerId, 'ownerId');

        $ownerId = $this->ownerId;

        $this->query
            ->addSelect([
                'elements_owners.ownerId as ownerId',
                'elements_owners.sortOrder as sortOrder',
            ])
            ->join(new Alias(CraftTable::ELEMENTS_OWNERS, 'elements_owners'), function(JoinClause $join) use ($ownerId) {
                $join->on('elements_owners.elementId', '=', 'elements.id');

                if ($ownerId) {
                    $join->whereIn('elements_owners.ownerId', $ownerId);
                } else {
                    $join->whereColumn('elements_owners.ownerId', 'commerce_variants.primaryOwnerId');
                }
            });

        $this->query->leftJoin(new Alias(Table::PRODUCTS, 'commerce_products'), 'commerce_products.id', '=', 'elements_owners.ownerId');
        $this->query->leftJoin(new Alias(Table::PRODUCTTYPES, 'commerce_producttypes'), 'commerce_producttypes.id', '=', 'commerce_products.typeId');
        $this->query->leftJoin(new Alias(CraftTable::ELEMENTS_SITES, 'commerce_products_elements_sites'), function(JoinClause $join) {
            $join->on('commerce_products_elements_sites.elementId', '=', 'elements_owners.ownerId')
                ->on('commerce_products_elements_sites.siteId', '=', 'elements_sites.siteId');
        });

        $this->query->addSelect([
            'commerce_products_elements_sites.slug as productSlug',
            'commerce_producttypes.handle as productTypeHandle',
        ]);

        // Whether this variant is its owner product's default variant.
        //
        // The legacy query derived this with a `CASE WHEN … END` select expression; the new element
        // query re-wraps any select expression as an identifier (see the note in PurchasableQuery),
        // so the same comparison is expressed as a joined subquery whose column is either the
        // variant's ID (truthy → default) or `null`.
        $this->query->leftJoinSub(
            DB::table(Table::PRODUCTS)
                ->select(['id as defaultForProductId', 'defaultVariantId'])
                ->whereNotNull('defaultVariantId'),
            'commerce_default_variants',
            function(JoinClause $join) {
                $join->on('commerce_default_variants.defaultVariantId', '=', 'commerce_variants.id')
                    ->on('commerce_default_variants.defaultForProductId', '=', 'commerce_products.id');
            },
        );

        $this->query->addSelect(['commerce_default_variants.defaultVariantId as isDefault']);
    }

    /**
     * Normalizes an owner ID param to an array of IDs or null.
     *
     * @return int[]|null
     *
     * @throws QueryAbortedException if the param value isn't a valid ID or set of IDs
     */
    private function normalizeOwnerIdParam(mixed $value, string $param): ?array
    {
        $normalized = $this->normalizeOwnerId($value);

        if ($normalized === false) {
            throw new QueryAbortedException("Invalid $param param value");
        }

        return $normalized;
    }

    /**
     * Applies the 'productStatus' param to the query being prepared.
     */
    private function applyProductStatusParam(): void
    {
        if (!$this->productStatus) {
            return;
        }

        // The owner product's element rows are only needed for this param
        $this->query->leftJoin(new Alias(CraftTable::ELEMENTS, 'product_elements'), 'product_elements.id', '=', 'commerce_variants.primaryOwnerId');
        $this->query->leftJoin(new Alias(CraftTable::ELEMENTS_SITES, 'product_elements_sites'), function(JoinClause $join) {
            $join->on('product_elements_sites.elementId', '=', 'commerce_variants.primaryOwnerId')
                ->on('product_elements_sites.siteId', '=', 'elements_sites.siteId');
        });

        // Normalize the product status param
        $statuses = is_array($this->productStatus)
            ? array_merge($this->productStatus)
            : str($this->productStatus)->explode(',')->all();

        $firstVal = strtolower((string)reset($statuses));
        if (in_array($firstVal, ['not', 'or'])) {
            $glue = $firstVal;
            array_shift($statuses);
            if (!$statuses) {
                return;
            }
        } else {
            $glue = 'or';
        }

        $negate = $glue === 'not';

        $this->where(function(Builder $query) use ($statuses, $negate) {
            foreach ($statuses as $status) {
                $condition = $this->productStatusCondition(strtolower((string)$status));

                if ($condition === null) {
                    throw new QueryAbortedException('Unsupported status: ' . $status);
                }

                if ($negate) {
                    $query->whereNot($condition);
                } else {
                    $query->orWhere($condition);
                }
            }
        });
    }

    /**
     * Returns the condition that the owner product must match for a given status.
     */
    private function productStatusCondition(string $status): ?Closure
    {
        $currentTime = now()->endOfMinute()->setTimezone('UTC');

        return match ($status) {
            Product::STATUS_LIVE => fn(Builder $query) => $query
                ->whereBool('product_elements.enabled', true)
                ->whereBool('product_elements_sites.enabled', true)
                ->where('commerce_products.postDate', '<=', $currentTime)
                ->where(function(Builder $query) use ($currentTime) {
                    $query->whereNull('commerce_products.expiryDate')
                        ->orWhere('commerce_products.expiryDate', '>', $currentTime);
                }
            ),
            Product::STATUS_PENDING => fn(Builder $query) => $query
                ->whereBool('product_elements.enabled', true)
                ->whereBool('product_elements_sites.enabled', true)
                ->where('commerce_products.postDate', '>', $currentTime),
            Product::STATUS_EXPIRED => fn(Builder $query) => $query
                ->whereBool('product_elements.enabled', true)
                ->whereBool('product_elements_sites.enabled', true)
                ->whereNotNull('commerce_products.expiryDate')
                ->where('commerce_products.expiryDate', '<=', $currentTime),
            Element::STATUS_ENABLED => fn(Builder $query) => $query
                ->whereBool('product_elements.enabled', true)
                ->whereBool('product_elements_sites.enabled', true),
            Element::STATUS_DISABLED => fn(Builder $query) => $query
                ->whereBool('product_elements.enabled', false)
                ->orWhere(fn(Builder $query) => $query->whereBool('product_elements_sites.enabled', false)),
            Element::STATUS_ARCHIVED => fn(Builder $query) => $query->whereBool('product_elements.archived', true),
            default => null,
        };
    }

    /**
     * Applies the 'hasProduct' param to the query being prepared.
     */
    private function applyHasProductParam(): void
    {
        if (!isset($this->hasProduct)) {
            return;
        }

        if ($this->hasProduct instanceof ProductQuery) {
            $productQuery = $this->hasProduct;
        } elseif (is_array($this->hasProduct)) {
            $productQuery = Product::find();
            self::configure($productQuery, ProductQueryHelper::cleanseQueryCriteria($this->hasProduct));
        } else {
            return;
        }

        $productQuery->limit(null);
        $productQuery->select('commerce_products.id as id');
        $productQuery->whereNotNull('commerce_products.id');
        $productQuery->applyBeforeQueryCallbacks();

        $this->whereIn('commerce_variants.primaryOwnerId', $productQuery->getQuery());
    }

    /**
     * Applies the 'hasSales' param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function applyHasSalesParam(): void
    {
        if (!isset($this->hasSales)) {
            return;
        }

        if (!app(Sales::class)->canUseSales()) {
            Deprecator::log('VariantQuery::hasSales', 'The `hasSales` parameter and Sales have been deprecated, use Pricing Rules instead.');
            throw new QueryAbortedException();
        }

        $nowDb = Query::prepareDateForDb(new DateTime());

        /** @var array<int, array<string, mixed>> $activeSales */
        $activeSales = DB::table(Table::SALES . ' as sales')
            ->select([
                'sales.id',
                'sales.allGroups',
                'sales.allPurchasables',
                'sales.allCategories',
                'sales.categoryRelationshipType',
            ])
            ->where(function(Builder $query) use ($nowDb) {
                $query
                    // Only a from date
                    ->where(fn(Builder $query) => $query
                        ->whereNull('dateTo')
                        ->whereNotNull('dateFrom')
                        ->where('dateFrom', '<=', $nowDb))
                    // Only a to date
                    ->orWhere(fn(Builder $query) => $query
                        ->whereNull('dateFrom')
                        ->whereNotNull('dateTo')
                        ->where('dateTo', '>=', $nowDb))
                    // No dates
                    ->orWhere(fn(Builder $query) => $query
                        ->whereNull('dateFrom')
                        ->whereNull('dateTo'))
                    // To and from dates
                    ->orWhere(fn(Builder $query) => $query
                        ->whereNotNull('dateFrom')
                        ->whereNotNull('dateTo')
                        ->where('dateFrom', '<=', $nowDb)
                        ->where('dateTo', '>=', $nowDb));
            })
            ->where('enabled', true)
            ->orderBy('sortOrder')
            ->get()
            ->map(fn(object $row) => (array)$row)
            ->all();

        foreach ($activeSales as $activeSale) {
            // A sale that matches every group, purchasable and category matches every variant,
            // so there's nothing left to narrow by
            if ($activeSale['allGroups'] && $activeSale['allPurchasables'] && $activeSale['allCategories']) {
                if ($this->hasSales) {
                    $this->whereBool('purchasables_stores.promotable', true);
                }

                return;
            }
        }

        $activeSaleIds = array_column($activeSales, 'id');

        // Only force user group restriction on site requests
        if (!request()->isCpRequest()) {
            $userGroupIds = [];

            // TODO: migrate to the new user API once the User element's groups are available on CraftUser
            if ($user = currentUserElement()) {
                $userGroupIds = array_column($user->getGroups(), 'id');
            }

            // If the user doesn't belong to any groups, remove sales that
            // restrict by user group as these would never match
            if (empty($userGroupIds)) {
                foreach ($activeSales as $activeSale) {
                    if (!$activeSale['allGroups']) {
                        $activeSaleIds = array_values(array_diff($activeSaleIds, [$activeSale['id']]));
                        break;
                    }
                }
            } else {
                // Exclude any sales that have a user group restriction that the current user is not part of
                $userGroupSalesIds = DB::table(Table::SALES . ' as sales')
                    ->select('sales.id')
                    ->leftJoin(Table::SALE_USERGROUPS . ' as su', 'su.saleId', '=', 'sales.id')
                    ->whereIn('sales.id', $activeSaleIds)
                    ->whereIn('userGroupId', $userGroupIds)
                    ->pluck('id')
                    ->all();

                foreach ($activeSales as $activeSale) {
                    if (!$activeSale['allGroups'] && !in_array($activeSale['id'], $userGroupSalesIds, false)) {
                        $activeSaleIds = array_values(array_diff($activeSaleIds, [$activeSale['id']]));
                    }
                }
            }
        }

        $activeSales = array_values(array_filter(
            $activeSales,
            fn(array $sale) => in_array($sale['id'], $activeSaleIds, false),
        ));

        // Check to see if we have any sales that match all products and categories
        // so we can skip extra processing if needed
        $allProductsAndCategoriesSales = array_filter(
            $activeSales,
            fn(array $sale) => $sale['allPurchasables'] && $sale['allCategories'],
        );

        /** @var array<int, Closure> $hasSalesConditions */
        $hasSalesConditions = [];

        if (empty($allProductsAndCategoriesSales)) {
            $purchasableRestrictedSaleIds = array_column(
                array_filter($activeSales, fn(array $sale) => !$sale['allPurchasables']),
                'id',
            );
            $categoryRestrictedSales = array_filter($activeSales, fn(array $sale) => !$sale['allCategories']);

            $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                'commerce_variants.id',
                DB::table(Table::SALE_PURCHASABLES . ' as sp')
                    ->select('purchasableId')
                    ->whereIn('saleId', $purchasableRestrictedSaleIds),
            );

            if (!empty($categoryRestrictedSales)) {
                $sourceSaleIds = array_column(array_filter($categoryRestrictedSales, fn(array $sale) => in_array($sale['categoryRelationshipType'], [
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
                ], true)), 'id');

                $targetSaleIds = array_column(array_filter($categoryRestrictedSales, fn(array $sale) => in_array($sale['categoryRelationshipType'], [
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
                ], true)), 'id');

                // Source relationships
                if (!empty($sourceSaleIds)) {
                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.primaryOwnerId',
                        $this->saleCategoryRelationsQuery($sourceSaleIds, 'sourceId', 'targetId', Product::class),
                    );

                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.id',
                        $this->saleCategoryRelationsQuery($sourceSaleIds, 'sourceId', 'targetId', Variant::class),
                    );
                }

                // Target relationships
                if (!empty($targetSaleIds)) {
                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.primaryOwnerId',
                        $this->saleCategoryRelationsQuery($targetSaleIds, 'targetId', 'sourceId', Product::class),
                    );

                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.id',
                        $this->saleCategoryRelationsQuery($targetSaleIds, 'targetId', 'sourceId', Variant::class),
                    );
                }
            }
        }

        if ($this->hasSales) {
            $this->whereBool('purchasables_stores.promotable', true);

            if (!empty($hasSalesConditions)) {
                $this->where(function(Builder $query) use ($hasSalesConditions) {
                    foreach ($hasSalesConditions as $condition) {
                        $query->orWhere($condition);
                    }
                });
            }
        } elseif (!empty($hasSalesConditions)) {
            $this->whereNot(function(Builder $query) use ($hasSalesConditions) {
                foreach ($hasSalesConditions as $condition) {
                    $query->orWhere($condition);
                }
            });
        }
    }

    /**
     * Returns a query for the IDs of elements of the given type that are related to any of the
     * given sales' categories.
     *
     * @param array<int, mixed> $saleIds
     * @param class-string $elementType
     */
    private function saleCategoryRelationsQuery(array $saleIds, string $selectColumn, string $joinColumn, string $elementType): Builder
    {
        return DB::table(Table::SALE_CATEGORIES . ' as sc')
            ->select("rel.$selectColumn")
            ->leftJoin(CraftTable::RELATIONS . ' as rel', "rel.$joinColumn", '=', 'sc.categoryId')
            ->leftJoin(CraftTable::ELEMENTS . ' as sale_elements', 'sale_elements.id', '=', "rel.$selectColumn")
            ->leftJoin(CraftTable::ELEMENTS_SITES . ' as sale_elements_sites', 'sale_elements_sites.elementId', '=', 'sc.categoryId')
            ->whereIn('sc.saleId', $saleIds)
            ->where('sale_elements.type', $elementType)
            ->when($this->siteId !== null, fn(Builder $query) => $query->whereParam('sale_elements_sites.siteId', $this->siteId))
            ->whereBool('sale_elements_sites.enabled', true);
    }

    /**
     * Applies an authorization param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function applyPermissionParam(?bool $value, string $permissionPrefix): void
    {
        if ($value === null) {
            return;
        }

        $user = currentUser();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // TODO: migrate to app(ProductTypes::class)->getAllProductTypes() once service migrated to src/
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        if (empty($productTypes)) {
            return;
        }

        $authorizedTypeIds = [];

        foreach ($productTypes as $productType) {
            if ($user->can("$permissionPrefix:$productType->uid")) {
                $authorizedTypeIds[] = $productType->id;
            }
        }

        if (count($authorizedTypeIds) === count($productTypes)) {
            // They have access to everything
            if (!$value) {
                throw new QueryAbortedException();
            }
            return;
        }

        if (empty($authorizedTypeIds)) {
            // They don't have access to anything
            if ($value) {
                throw new QueryAbortedException();
            }
            return;
        }

        if ($value) {
            $this->whereIn('commerce_products.typeId', $authorizedTypeIds);
        } else {
            $this->whereNotIn('commerce_products.typeId', $authorizedTypeIds);
        }
    }

    #[Override]
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->ownerId) {
            foreach (Arr::wrap($this->ownerId) as $ownerId) {
                $tags[] = "product:$ownerId";
            }
        }

        array_push($tags, ...$this->nestedTraitCacheTags());

        return $tags;
    }
}
