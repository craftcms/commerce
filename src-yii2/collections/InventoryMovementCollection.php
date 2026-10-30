<?php

namespace craft\commerce\collections;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection} */
class_alias(\CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection::class, 'craft\commerce\collections\InventoryMovementCollection');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryMovementCollection extends \CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection {}
}
