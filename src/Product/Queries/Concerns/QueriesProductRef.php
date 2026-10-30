<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Queries\Concerns;

use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Product reference-string scope, mirroring {@see \CraftCms\Cms\Element\Queries\Concerns\Entry\QueriesRef}
 * — see {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait
 * convention this follows.
 *
 * @internal
 */
trait QueriesProductRef
{
    /**
     * The reference code(s) used to identify the product(s), e.g. `{product:productTypeHandle/slug}`.
     */
    public mixed $ref = null;

    protected function initQueriesProductRef(): void
    {
        $this->beforeQuery(static function(ProductQuery $productQuery) {
            static::applyRef($productQuery, $productQuery->ref);
        });
    }

    public static function applyRef(BuilderContract $query, mixed $value): void
    {
        if (!$value) {
            return;
        }

        $refs = Arr::wrap($value);
        $joinProductTypes = false;

        $query->where(function(Builder $query) use (&$joinProductTypes, $refs) {
            foreach ($refs as $ref) {
                $parts = array_filter(explode('/', (string)$ref), static fn(string $part) => $part !== '');

                if (empty($parts)) {
                    continue;
                }

                if (count($parts) === 1) {
                    $query->orWhereParam('elements_sites.slug', reset($parts));
                    continue;
                }

                $parts = array_values($parts);

                $query->orWhere(function(Builder $query) use ($parts) {
                    $query->whereParam('commerce_producttypes.handle', $parts[0])
                        ->whereParam('elements_sites.slug', $parts[1]);
                });

                $joinProductTypes = true;
            }
        });

        if ($joinProductTypes) {
            $query->join(new Alias(Table::PRODUCTTYPES, 'commerce_producttypes'), 'commerce_producttypes.id', '=', 'commerce_products.typeId');
        }
    }

    /**
     * Narrows the query results based on a reference string.
     */
    public function ref(mixed $value): static
    {
        $this->ref = $value;
        return $this;
    }
}
