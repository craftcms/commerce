<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryManualMovement} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryManualMovement::class, 'craft\commerce\models\inventory\InventoryManualMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryManualMovement extends \CraftCms\Commerce\Inventory\Models\InventoryManualMovement {}
}
