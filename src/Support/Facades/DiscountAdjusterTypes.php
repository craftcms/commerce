<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void defer(\Closure $callback)
 * @method static void register(string ...$types)
 * @method static void remove(string ...$types)
 * @method static \Illuminate\Support\Collection types()
 *
 * @see \CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes
 */
class DiscountAdjusterTypes extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes::class;
    }
}
