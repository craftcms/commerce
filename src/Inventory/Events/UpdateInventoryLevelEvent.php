<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;

class UpdateInventoryLevelEvent
{
    public function __construct(
        public UpdateInventoryLevel $updateInventoryLevel,
    ) {
    }
}
