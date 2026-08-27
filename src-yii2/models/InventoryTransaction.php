<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryTransaction} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryTransaction::class, 'craft\commerce\models\InventoryTransaction');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryTransaction extends \CraftCms\Commerce\Inventory\Data\InventoryTransaction {}
}
