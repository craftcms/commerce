<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster;

use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers order adjuster type classes that run against every order.
 *
 * ```php
 * public function boot(AdjusterTypes $adjusterTypes): void
 * {
 *     $adjusterTypes->register(MyAdjuster::class);
 * }
 * ```
 *
 * @extends TypeRegistry<AdjusterInterface>
 */
#[Singleton]
class AdjusterTypes extends TypeRegistry
{
    protected const ?string CONTRACT = AdjusterInterface::class;

    protected const array DEFAULT_TYPES = [
        Shipping::class,
    ];
}
