<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Thin Eloquent persistence model for the `commerce_shippingcategories` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Shipping\ShippingCategories} to read/write rows, which are then
 * hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Shipping\Data\ShippingCategory} object that the rest of the
 * codebase actually works with.
 */
class ShippingCategory extends BaseModel
{
    use SoftDeletes;

    #[\Override]
    protected $table = Table::SHIPPINGCATEGORIES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'default' => 'boolean',
    ];
}
