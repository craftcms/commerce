<?php

namespace craft\commerce\models\inventory;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryCommittedMovement} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryCommittedMovement::class, 'craft\commerce\models\inventory\InventoryCommittedMovement');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryCommittedMovement extends \CraftCms\Commerce\Inventory\Data\InventoryCommittedMovement {}
}
