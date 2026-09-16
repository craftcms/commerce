<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable;

use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers purchasable element type classes.
 *
 * ```php
 * public function boot(PurchasableTypes $purchasableTypes): void
 * {
 *     $purchasableTypes->register(MyPurchasable::class);
 * }
 * ```
 *
 * @extends TypeRegistry<PurchasableInterface>
 */
#[Singleton]
class PurchasableTypes extends TypeRegistry
{
    protected const ?string CONTRACT = PurchasableInterface::class;

    protected const array DEFAULT_TYPES = [
        Variant::class,
    ];
}
