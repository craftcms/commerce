<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel} */
class_alias(\CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel::class, 'craft\commerce\models\InventoryFulfillmentLevel');

/** @phpstan-ignore-next-line */
if (false) {
    class InventoryFulfillmentLevel extends \CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel {}
}
