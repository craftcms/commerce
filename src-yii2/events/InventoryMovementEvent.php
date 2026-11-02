<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Inventory\Events\InventoryMoved} instead.
 */
class InventoryMovementEvent extends Event
{
    /**
     * @var InventoryMovementInterface The inventory movement
     */
    public InventoryMovementInterface $inventoryMovement;
}
