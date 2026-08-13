<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;

class InventoryLevel extends Component
{
    public int $inventoryItemId;

    public int $inventoryLocationId;

    public int $availableTotal = 0;

    public int $committedTotal = 0;

    public int $reservedTotal = 0;

    public int $damagedTotal = 0;

    public int $safetyTotal = 0;

    public int $qualityControlTotal = 0;

    public int $incomingTotal = 0;

    public int $fulfilledTotal = 0;

    public int $unavailableTotal = 0;

    public int $onHandTotal = 0;

    private ?InventoryItem $_inventoryItem = null;

    public function getTotal(InventoryTransactionType $type): int
    {
        return $this->{$type->value . 'Total'};
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/inventory/levels');
    }

    public function getInventoryItem(): InventoryItem
    {
        if ($this->_inventoryItem === null) {
            $this->_inventoryItem = app(Inventory::class)->getInventoryItemById($this->inventoryItemId);
        }
        return $this->_inventoryItem;
    }

    public function setInventoryItem(InventoryItem $inventoryItem): void
    {
        $this->_inventoryItem = $inventoryItem;
        $this->inventoryItemId = $inventoryItem->id;
    }

    public function getInventoryLocation(): InventoryLocation
    {
        return app(InventoryLocations::class)->getInventoryLocationById($this->inventoryLocationId);
    }

    public function getPurchasable(null|string|int $siteId = null): PurchasableInterface
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
