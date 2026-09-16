<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_producttypes_sites` table.
 *
 * This holds no business logic — it's read/written by {@see \CraftCms\Commerce\Product\ProductType\ProductTypes}
 * and hydrated into (or persisted from) the rich {@see \CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite}
 * data object that the rest of the codebase actually works with.
 */
class ProductTypeSite extends BaseModel
{
    #[\Override]
    protected $table = Table::PRODUCTTYPES_SITES;

    public $timestamps = false;

    #[\Override]
    protected $casts = [
        'productTypeId' => 'integer',
        'siteId' => 'integer',
        'hasUrls' => 'boolean',
        'enabledByDefault' => 'boolean',
    ];
}
