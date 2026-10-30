<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Queries\Concerns;

use Closure;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Commerce\Product\Elements\Product;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Scope narrowing variants by their owning product's status — see
 * {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * NOTE: no `init*()` beforeQuery registration of its own — see the equivalent note on
 * {@see \CraftCms\Commerce\Product\Variant\Queries\Concerns\QueriesVariantProductType}.
 * `applyProductStatus()`'s status conditions reference `commerce_products.postDate`/`expiryDate`,
 * columns joined by `joinOwners()`.
 *
 * @internal
 */
trait QueriesVariantProductStatus
{
    /**
     * The status the owner product must have.
     *
     * @var array<int, string>|string|null
     */
    public array|string|null $productStatus = null;

    /** @param array<int, string>|string|null $value */
    public static function applyProductStatus(BuilderContract $query, array|string|null $value): void
    {
        if (!$value) {
            return;
        }

        // The owner product's element rows are only needed for this param
        $query->leftJoin(new Alias(CraftTable::ELEMENTS, 'product_elements'), 'product_elements.id', '=', 'commerce_variants.primaryOwnerId');
        $query->leftJoin(new Alias(CraftTable::ELEMENTS_SITES, 'product_elements_sites'), function($join) {
            $join->on('product_elements_sites.elementId', '=', 'commerce_variants.primaryOwnerId')
                ->on('product_elements_sites.siteId', '=', 'elements_sites.siteId');
        });

        // Normalize the product status param
        $statuses = is_array($value)
            ? array_merge($value)
            : str($value)->explode(',')->all();

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

        $query->where(function(Builder $query) use ($statuses, $negate) {
            foreach ($statuses as $status) {
                $condition = self::productStatusCondition(strtolower((string)$status));

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
    private static function productStatusCondition(string $status): ?Closure
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
     * Narrows the query results based on the variants’ products’ statuses.
     *
     * @param string|string[]|null $value
     */
    public function productStatus(array|string|null $value): static
    {
        $this->productStatus = $value;
        return $this;
    }
}
