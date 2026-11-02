<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Inventory\Events\InventoryLevelUpdated} instead.
 */
class UpdateInventoryLevelEvent extends Event
{
    /**
     * @var UpdateInventoryLevel The inventory level update
     */
    public UpdateInventoryLevel $updateInventoryLevel;
}
