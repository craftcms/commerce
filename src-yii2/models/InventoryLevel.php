<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryLevel} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryLevel::class, 'craft\commerce\models\InventoryLevel');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryLevel extends \CraftCms\Commerce\Inventory\Models\InventoryLevel {}
}
