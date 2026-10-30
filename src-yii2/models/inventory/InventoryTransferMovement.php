<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryTransferMovement} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryTransferMovement::class, 'craft\commerce\models\inventory\InventoryTransferMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryTransferMovement extends \CraftCms\Commerce\Inventory\Data\InventoryTransferMovement {}
}
