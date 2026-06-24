<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use craft\commerce\base\Purchasable;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;

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
            // TODO: migrate to app(Inventory::class)->getInventoryItemById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
        }
        return $this->_inventoryItem;
    }

    public function setInventoryItem(InventoryItem $inventoryItem): void
    {
        $this->_inventoryItem = $inventoryItem;
        $this->inventoryItemId = $inventoryItem->id;
    }

    public function getInventoryLocation(): \craft\commerce\models\InventoryLocation
    {
        // TODO: migrate to app(InventoryLocations::class)->getInventoryLocationById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->inventoryLocationId);
    }

    public function getPurchasable(null|string|int $siteId = null): Purchasable
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
