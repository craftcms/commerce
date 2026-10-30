<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Concerns;

use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\InventoryLocations;

trait InventoryLocationTrait
{
    public ?int $inventoryLocationId = null;

    private ?InventoryLocation $_inventoryLocation = null;

    public function setInventoryLocation(?InventoryLocation $inventoryLocation): void
    {
        $this->_inventoryLocation = $inventoryLocation;
        $this->inventoryLocationId = $inventoryLocation?->id;
    }

    public function getInventoryLocation(): ?InventoryLocation
    {
        if (isset($this->_inventoryLocation)) {
            return $this->_inventoryLocation;
        }

        if ($this->inventoryLocationId) {
            $this->_inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById($this->inventoryLocationId);
            return $this->_inventoryLocation;
        }

        return null;
    }
}
