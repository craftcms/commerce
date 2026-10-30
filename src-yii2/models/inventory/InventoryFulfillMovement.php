<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement::class, 'craft\commerce\models\inventory\InventoryFulfillMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryFulfillMovement extends \CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement {}
}
