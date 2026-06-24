<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryItem} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryItem::class, 'craft\commerce\models\InventoryItem');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryItem extends \CraftCms\Commerce\Inventory\Models\InventoryItem {}
}
