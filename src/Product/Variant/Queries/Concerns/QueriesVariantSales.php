<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Queries\Concerns;

use Closure;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Query;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Models\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Sales;
use DateTime;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\currentUserElement;

/**
 * Scope narrowing variants to those on sale, mirroring the (deprecated, superseded by Pricing
 * Rules) legacy Sales feature — see {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity}
 * for the trait convention this follows.
 *
 * NOTE: no `init*()` beforeQuery registration of its own — see the equivalent note on
 * {@see \CraftCms\Commerce\Product\Variant\Queries\Concerns\QueriesVariantProductType}.
 * `applyHasSales()` references `purchasables_stores.promotable`/`commerce_variants.*` columns
 * whose joins must already be in place.
 *
 * @internal
 */
trait QueriesVariantSales
{
    public ?bool $hasSales = null;

    /**
     * @throws QueryAbortedException
     */
    public static function applyHasSales(BuilderContract $query, ?bool $value, mixed $siteId): void
    {
        if (!isset($value)) {
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
                if ($value) {
                    $query->whereBool('purchasables_stores.promotable', true);
                }

                return;
            }
        }

        $activeSaleIds = array_column($activeSales, 'id');

        // Only force user group restriction on site requests
        if (!request()->isCpRequest()) {
            $userGroupIds = [];

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
                        self::saleCategoryRelationsQuery($sourceSaleIds, 'sourceId', 'targetId', Product::class, $siteId),
                    );

                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.id',
                        self::saleCategoryRelationsQuery($sourceSaleIds, 'sourceId', 'targetId', Variant::class, $siteId),
                    );
                }

                // Target relationships
                if (!empty($targetSaleIds)) {
                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.primaryOwnerId',
                        self::saleCategoryRelationsQuery($targetSaleIds, 'targetId', 'sourceId', Product::class, $siteId),
                    );

                    $hasSalesConditions[] = fn(Builder $query) => $query->whereIn(
                        'commerce_variants.id',
                        self::saleCategoryRelationsQuery($targetSaleIds, 'targetId', 'sourceId', Variant::class, $siteId),
                    );
                }
            }
        }

        if ($value) {
            $query->whereBool('purchasables_stores.promotable', true);

            if (!empty($hasSalesConditions)) {
                $query->where(function(Builder $query) use ($hasSalesConditions) {
                    foreach ($hasSalesConditions as $condition) {
                        $query->orWhere($condition);
                    }
                });
            }
        } elseif (!empty($hasSalesConditions)) {
            $query->whereNot(function(Builder $query) use ($hasSalesConditions) {
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
    private static function saleCategoryRelationsQuery(array $saleIds, string $selectColumn, string $joinColumn, string $elementType, mixed $siteId): Builder
    {
        return DB::table(Table::SALE_CATEGORIES . ' as sc')
            ->select("rel.$selectColumn")
            ->leftJoin(CraftTable::RELATIONS . ' as rel', "rel.$joinColumn", '=', 'sc.categoryId')
            ->leftJoin(CraftTable::ELEMENTS . ' as sale_elements', 'sale_elements.id', '=', "rel.$selectColumn")
            ->leftJoin(CraftTable::ELEMENTS_SITES . ' as sale_elements_sites', 'sale_elements_sites.elementId', '=', 'sc.categoryId')
            ->whereIn('sc.saleId', $saleIds)
            ->where('sale_elements.type', $elementType)
            ->when($siteId !== null, fn(Builder $query) => $query->whereParam('sale_elements_sites.siteId', $siteId))
            ->whereBool('sale_elements_sites.enabled', true);
    }

    /**
     * Narrows the query results to only variants that are on sale.
     */
    public function hasSales(?bool $value = true): static
    {
        $this->hasSales = $value;
        return $this;
    }
}
