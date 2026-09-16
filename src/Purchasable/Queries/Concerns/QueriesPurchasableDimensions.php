<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries\Concerns;

use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Purchasable physical-dimension scopes — see {@see QueriesPurchasablePricing} for the trait
 * convention this follows.
 *
 * @internal
 */
trait QueriesPurchasableDimensions
{
    public mixed $width = false;

    public mixed $height = false;

    public mixed $length = false;

    public mixed $weight = false;

    protected function initQueriesPurchasableDimensions(): void
    {
        $this->beforeQuery(static function(PurchasableQuery $purchasableQuery) {
            static::applyWidth($purchasableQuery, $purchasableQuery->width);
            static::applyHeight($purchasableQuery, $purchasableQuery->height);
            static::applyLength($purchasableQuery, $purchasableQuery->length);
            static::applyWeight($purchasableQuery, $purchasableQuery->weight);
        });
    }

    public static function applyWidth(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.width');
        } else {
            $query->whereParam('commerce_purchasables.width', $value);
        }
    }

    public static function applyHeight(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.height');
        } else {
            $query->whereParam('commerce_purchasables.height', $value);
        }
    }

    public static function applyLength(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.length');
        } else {
            $query->whereParam('commerce_purchasables.length', $value);
        }
    }

    public static function applyWeight(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.weight');
        } else {
            $query->whereParam('commerce_purchasables.weight', $value);
        }
    }

    public function width(mixed $value): static
    {
        $this->width = $value;
        return $this;
    }

    public function height(mixed $value): static
    {
        $this->height = $value;
        return $this;
    }

    public function length(mixed $value): static
    {
        $this->length = $value;
        return $this;
    }

    public function weight(mixed $value): static
    {
        $this->weight = $value;
        return $this;
    }
}
