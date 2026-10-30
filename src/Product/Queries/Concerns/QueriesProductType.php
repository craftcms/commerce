<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Queries\Concerns;

use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Product-type scope, mirroring the `QueriesFields`/`QueriesAssetLocation` convention from craft
 * core — see {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for why
 * condition rules must call `applyTypeId()` directly rather than the fluent `type()`/`typeId()`
 * setters.
 *
 * @internal
 */
trait QueriesProductType
{
    public mixed $typeId = null;

    protected function initQueriesProductType(): void
    {
        $this->beforeQuery(static function(ProductQuery $productQuery) {
            $productQuery->normalizeTypeId();

            // See if 'type' was set to an invalid handle
            if ($productQuery->typeId === []) {
                throw new QueryAbortedException();
            }

            static::applyTypeId($productQuery, $productQuery->typeId, $productQuery);
        });
    }

    /**
     * @param array<int>|null $typeId
     */
    public static function applyTypeId(BuilderContract $query, mixed $typeId, ProductQuery $productQuery): void
    {
        if (!$typeId) {
            return;
        }

        $query->whereIn('commerce_products.typeId', $typeId);

        // Should we set the structureId param?
        if (
            $productQuery->withStructure !== false &&
            !isset($productQuery->structureId) &&
            count($typeId) === 1
        ) {
            $productType = app(ProductTypes::class)->getProductTypeById((int)reset($typeId));

            if ($productType && $productType->isStructure) {
                $productQuery->structureId = $productType->structureId;
            } else {
                $productQuery->withStructure = false;
            }
        }
    }

    /**
     * Narrows the query results based on the products’ types.
     *
     * @param ProductType|string|string[]|null $value
     */
    public function type(mixed $value): static
    {
        if (is_string($value) && ($productType = app(ProductTypes::class)->getProductTypeByHandle($value))) {
            $value = $productType;
        }

        if ($value instanceof ProductType) {
            $this->typeId = [$value->id];
        } elseif ($value !== null) {
            $this->typeId = DB::table(Table::PRODUCTTYPES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the products’ types, per the types’ IDs.
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Normalizes the typeId param to an array of IDs or null.
     */
    private function normalizeTypeId(): void
    {
        if (empty($this->typeId)) {
            $this->typeId = is_array($this->typeId) ? [] : null;
        } elseif (is_numeric($this->typeId)) {
            $this->typeId = [$this->typeId];
        } elseif (!is_array($this->typeId) || !Arr::isNumeric($this->typeId)) {
            $this->typeId = DB::table(Table::PRODUCTTYPES)
                ->whereParam('id', $this->typeId)
                ->pluck('id')
                ->all();
        }
    }
}
