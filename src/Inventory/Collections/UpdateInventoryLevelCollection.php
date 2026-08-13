<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Collections;

use CraftCms\Commerce\Inventory\Models\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Models\UpdateInventoryLevelInTransfer;
use Illuminate\Support\Collection;

/**
 * UpdateInventoryLevelCollection represents a collection of UpdateInventoryLevel models.
 */
class UpdateInventoryLevelCollection extends Collection
{
    /**
     * Creates a UpdateInventoryLevelCollection from an array of UpdateInventoryLevel attributes.
     */
    public static function make($items = [], ...$args)
    {
        foreach ($items as &$item) {
            if ($item instanceof UpdateInventoryLevel) {
                continue;
            }

            $item = new UpdateInventoryLevel($item);
        }

        /** @var static $collection */
        $collection = parent::make($items);
        return $collection;
    }

    public function getPurchasables(): array
    {
        return $this->map(fn(UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel) => $updateInventoryLevel->getInventoryItem()->getPurchasable())->filter()->all();
    }
}
