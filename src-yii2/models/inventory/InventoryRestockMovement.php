<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryRestockMovement} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryRestockMovement::class, 'craft\commerce\models\inventory\InventoryRestockMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryRestockMovement extends \CraftCms\Commerce\Inventory\Data\InventoryRestockMovement {}
}
