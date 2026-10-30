<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Events;

use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use yii\base\Event;

class UpdateInventoryLevelEvent extends Event
{
    public function __construct(
        public UpdateInventoryLevel $updateInventoryLevel,
    ) {
    }
}
