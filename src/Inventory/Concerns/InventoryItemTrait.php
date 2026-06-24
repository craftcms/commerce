<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Concerns;

use craft\commerce\Plugin;
use CraftCms\Commerce\Inventory\Models\InventoryItem;

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
            // TODO: migrate to app(Inventory::class)->getInventoryItemById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
            return $this->_inventoryItem;
        }

        return null;
    }
}
