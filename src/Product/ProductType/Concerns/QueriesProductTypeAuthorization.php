<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Concerns;

use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

use function CraftCms\Cms\currentUser;

/**
 * Product-type-permission scopes, shared by {@see \CraftCms\Commerce\Product\Queries\ProductQuery}
 * and {@see \CraftCms\Commerce\Product\Variant\Queries\VariantQuery} (a variant's editable/savable
 * status is governed by its product's type, same as a product's own) — see
 * {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesProductTypeAuthorization
{
    /**
     * Whether to only return results that the user has permission to view.
     */
    public ?bool $editable = null;

    /**
     * Whether to only return results that the user has permission to save.
     */
    public ?bool $savable = null;

    protected function initQueriesProductTypeAuthorization(): void
    {
        $this->beforeQuery(static function(self $query) {
            // Mirrors EntryQuery: "editable" means accessible in the editing UI (view permission),
            // not necessarily savable. Use ->savable() to filter by save permission.
            static::applyEditable($query, $query->editable);
            static::applySavable($query, $query->savable);
        });
    }

    public static function applyEditable(BuilderContract $query, ?bool $value): void
    {
        self::applyProductTypeAuthParam($query, $value, 'commerce-viewProductType');
    }

    public static function applySavable(BuilderContract $query, ?bool $value): void
    {
        self::applyProductTypeAuthParam($query, $value, 'commerce-saveProductType');
    }

    /**
     * @throws QueryAbortedException
     */
    private static function applyProductTypeAuthParam(BuilderContract $query, ?bool $value, string $permissionPrefix): void
    {
        if ($value === null) {
            return;
        }

        $user = currentUser();

        if (!$user) {
            throw new QueryAbortedException();
        }

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
            $query->whereIn('commerce_products.typeId', $authorizedTypeIds);
        } else {
            $query->whereNotIn('commerce_products.typeId', $authorizedTypeIds);
        }
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
}
