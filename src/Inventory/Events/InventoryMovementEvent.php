<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;
use yii\base\Event;

class InventoryMovementEvent extends Event
{
    public function __construct(
        public InventoryMovementInterface $inventoryMovement,
    ) {
    }
}
