<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster;

use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers adjuster type classes that should be treated as discounts, e.g. for "discounted item subtotal"
 * calculations.
 *
 * ```php
 * public function boot(DiscountAdjusterTypes $discountAdjusterTypes): void
 * {
 *     $discountAdjusterTypes->register(MyDiscountAdjuster::class);
 * }
 * ```
 *
 * @extends TypeRegistry<AdjusterInterface>
 */
#[Singleton]
class DiscountAdjusterTypes extends TypeRegistry
{
    protected const ?string CONTRACT = AdjusterInterface::class;

    protected const array DEFAULT_TYPES = [
        Discount::class,
    ];
}
