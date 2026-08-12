<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use craft\commerce\models\inventory\UpdateInventoryLevel;

class UpdateInventoryLevelEvent
{
    public function __construct(
        public UpdateInventoryLevel $updateInventoryLevel,
    ) {}
}
