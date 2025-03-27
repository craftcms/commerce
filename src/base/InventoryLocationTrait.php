<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;

/**
 * Inventory Location Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
trait InventoryLocationTrait
{
    /**
     * @var int|null The inventory item ID
     */
    public ?int $inventoryLocationId = null;

    /**
     * @var InventoryLocation|null The inventory item
     * @see getInventoryLocation()
     * @see setInventoryLocation()
     */
    private ?InventoryLocation $_inventoryLocation = null;

    /**
     * @param InventoryLocation|null $inventoryLocation
     * @return void
     */
    public function setInventoryLocation(?InventoryLocation $inventoryLocation): void
    {
        $this->_inventoryLocation = $inventoryLocation;
        $this->inventoryLocationId = $inventoryLocation?->id ?? null;
    }

    /**
     * @return InventoryLocation|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getInventoryLocation(): ?InventoryLocation
    {
        if (isset($this->_inventoryLocation)) {
            return $this->_inventoryLocation;
        }

        if ($this->inventoryLocationId) {
            $this->_inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->inventoryLocationId);

            return $this->_inventoryLocation;
        }

        return null;
    }
}
