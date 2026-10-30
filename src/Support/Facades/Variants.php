<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Product\Variant\Elements\Variant[] getAllVariantsByProductId(int $productId, int|null $siteId = null, bool $includeDisabled = true)
 * @method static \CraftCms\Commerce\Product\Variant\Elements\Variant|null getVariantById(int $variantId, int|null $siteId = null)
 * @method static array getVariantGqlContentArguments()
 *
 * @see \CraftCms\Commerce\Product\Variant\Variants
 */
class Variants extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Product\Variant\Variants::class;
    }
}
