<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryMovement} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryMovement::class, 'craft\commerce\base\InventoryMovement');

/** @phpstan-ignore-next-line */
if (false) {
    abstract class InventoryMovement extends \CraftCms\Commerce\Inventory\Models\InventoryMovement {}
}
