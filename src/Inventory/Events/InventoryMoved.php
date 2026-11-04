<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;

/**
 * Dispatched after inventory is moved.
 */
class InventoryMoved
{
    public function __construct(
        public InventoryMovementInterface $inventoryMovement,
    ) {
    }
}
