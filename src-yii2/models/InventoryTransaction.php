<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryTransaction} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryTransaction::class, 'craft\commerce\models\InventoryTransaction');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryTransaction extends \CraftCms\Commerce\Inventory\Models\InventoryTransaction {}
}
