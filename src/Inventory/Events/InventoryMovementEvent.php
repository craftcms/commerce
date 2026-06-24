<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;

class InventoryMovementEvent
{
    public InventoryMovementInterface $inventoryMovement;
}
