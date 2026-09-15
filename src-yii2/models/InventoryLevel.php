<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryLevel} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryLevel::class, 'craft\commerce\models\InventoryLevel');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryLevel extends \CraftCms\Commerce\Inventory\Data\InventoryLevel {}
}
