<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;

/**
 * Dispatched after an inventory level is updated.
 */
class InventoryLevelUpdated
{
    public function __construct(
        public UpdateInventoryLevel $updateInventoryLevel,
    ) {
    }
}
