<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Product\Elements\Product|null getProductById(int $id, array|int|string|null $siteId = null, array $criteria = [])
 * @method static void afterSaveSiteHandler(\CraftCms\Cms\Site\Events\SiteSaved $event)
 *
 * @see \CraftCms\Commerce\Product\Products
 */
class Products extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Product\Products::class;
    }
}
