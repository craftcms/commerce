<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Concerns;

use CraftCms\Commerce\Inventory\Models\InventoryItem;
use CraftCms\Commerce\Services\Inventory;

trait InventoryItemTrait
{
    public ?int $inventoryItemId = null;

    private ?InventoryItem $_inventoryItem = null;

    public function setInventoryItem(?InventoryItem $inventoryItem): void
    {
        $this->_inventoryItem = $inventoryItem;
        $this->inventoryItemId = $inventoryItem?->id;
    }

    public function getInventoryItem(): ?InventoryItem
    {
        if (isset($this->_inventoryItem)) {
            return $this->_inventoryItem;
        }

        if ($this->inventoryItemId) {
            $this->_inventoryItem = app(Inventory::class)->getInventoryItemById($this->inventoryItemId);
            return $this->_inventoryItem;
        }

        return null;
    }
}
