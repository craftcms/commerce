<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool isValidVatId(string $vatId)
 *
 * @see \CraftCms\Commerce\Tax\Vat
 */
class Vat extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Tax\Vat::class;
    }
}
