<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Queries\Concerns;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Scope narrowing variants by their owning product's type — see
 * {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * NOTE: unlike most of these traits, this one has no `init*()` beforeQuery registration of its
 * own — `applyTypeId()` references `commerce_products.typeId`, a column only available once
 * {@see \CraftCms\Commerce\Product\Variant\Queries\VariantQuery::joinOwners()} has run, and that
 * join is itself deferred to VariantQuery's own beforeQuery callback (registered in its
 * constructor, so it always fires after any trait-registered one). VariantQuery calls
 * `applyTypeId()` directly, after `joinOwners()`, instead.
 *
 * @internal
 */
trait QueriesVariantProductType
{
    public mixed $typeId = null;

    public static function applyTypeId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_products.typeId', $value);
    }

    /**
     * Narrows the query results based on the variants’ product types, per their IDs.
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
    }
}
