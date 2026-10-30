<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_inventoryitems` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Inventory\Inventory} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Inventory\Data\InventoryItem}
 * object that the rest of the codebase actually works with.
 */
class InventoryItem extends BaseModel
{
    #[\Override]
    protected $table = Table::INVENTORYITEMS;

    #[\Override]
    protected $casts = [
        'purchasableId' => 'integer',
    ];
}
