<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Concerns;

use craft\commerce\Plugin;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;

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
            // TODO: migrate to app(InventoryLocations::class)->getInventoryLocationById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->inventoryLocationId);
            return $this->_inventoryLocation;
        }

        return null;
    }
}
