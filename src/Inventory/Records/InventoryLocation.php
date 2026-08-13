<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Thin Eloquent persistence model for the `commerce_inventorylocations` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Inventory\InventoryLocations} to read/write rows, which are then
 * hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Inventory\Models\InventoryLocation} object that the rest of the
 * codebase actually works with.
 */
class InventoryLocation extends BaseModel
{
    use SoftDeletes;

    #[\Override]
    protected $table = Table::INVENTORYLOCATIONS;

    #[\Override]
    protected $casts = [
        'addressId' => 'integer',
    ];
}
