<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Collections;

use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;
use CraftCms\Commerce\Inventory\Data\InventoryMovement;
use Illuminate\Support\Collection;

/**
 * InventoryMovementCollection represents a collection of InventoryMovementInterface models.
 *
 * @template TValue of InventoryMovement
 * @extends Collection<array-key, TValue>
 * @method static self make($items = [])
 */
class InventoryMovementCollection extends Collection
{
    public function getPurchasables(): array
    {
        return $this->map(fn(InventoryMovementInterface $updateInventoryLevel) => $updateInventoryLevel->getInventoryItem()->getPurchasable())->all();
    }
}
