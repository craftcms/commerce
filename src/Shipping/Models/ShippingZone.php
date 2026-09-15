<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_shippingzones` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Shipping\ShippingZones} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Shipping\Data\ShippingAddressZone}
 * object that the rest of the codebase actually works with.
 */
class ShippingZone extends BaseModel
{
    #[\Override]
    protected $table = Table::SHIPPINGZONES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'condition' => 'array',
    ];
}
