<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Models\InventoryLocation} */
class_alias(\CraftCms\Commerce\Inventory\Models\InventoryLocation::class, 'craft\commerce\models\InventoryLocation');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryLocation extends \CraftCms\Commerce\Inventory\Models\InventoryLocation {}
}
