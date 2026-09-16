<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_shippingmethods` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Shipping\ShippingMethods} to read/write rows, which are then
 * hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Shipping\Data\ShippingMethod} object that the rest of the codebase
 * actually works with.
 */
class ShippingMethod extends BaseModel
{
    #[\Override]
    protected $table = Table::SHIPPINGMETHODS;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'enabled' => 'boolean',
        'orderCondition' => 'array',
        'customerCondition' => 'array',
    ];
}
