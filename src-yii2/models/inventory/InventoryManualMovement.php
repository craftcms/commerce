<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryManualMovement} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryManualMovement::class, 'craft\commerce\models\inventory\InventoryManualMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryManualMovement extends \CraftCms\Commerce\Inventory\Data\InventoryManualMovement {}
}
