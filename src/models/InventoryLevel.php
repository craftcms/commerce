<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\enums\InventoryMovementType;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Inventory Item model
 */
class InventoryLevel extends Model
{
    /**
     * @var int
     */
    public int $inventoryItemId;

    /**
     * @var int
     */
    public int $inventoryLocationId;

    /**
     * @var int The total available stock
     */
    public int $availableTotal = 0;

    /**
     * @var int The total committed stock
     */
    public int $committedTotal = 0;

    /**
     * @var int The total reserved stock
     */
    public int $reservedTotal = 0;

    /**
     * @var int The total damaged stock
     */
    public int $damagedTotal = 0;

    /**
     * @var int The total safety stock
     */
    public int $safetyTotal = 0;

    /**
     * @var int The total quality control stock
     */
    public int $qualityControlTotal = 0;

    /**
     * @var int The total incoming stock
     */
    public int $incomingTotal = 0;

    /**
     * @var int The total unavailable stock
     */
    public int $unavailableTotal = 0;

    /**
     *
     * @var int The total on hand stock
     */
    public int $onHandTotal = 0;

    /**
     * @var InventoryItem|null The inventory item associated with this inventory level
     */
    private ?InventoryItem $_inventoryItem = null;

    /**
     * @param InventoryMovementType $type
     * @return int
     */
    public function getTotal(InventoryMovementType $type): int
    {
        return $this->{$type->value . 'Total'};
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/inventory/levels');
    }

    /**
     * @return InventoryItem
     */
    public function getInventoryItem(): InventoryItem
    {
        if($this->_inventoryItem === null) {
            $this->_inventoryItem =  Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);;
        }
        return $this->_inventoryItem;
    }

    /**
     * @param InventoryItem $inventoryItem
     * @return void
     */
    public function setInventoryItem(InventoryItem $inventoryItem): void
    {
        $this->_inventoryItem = $inventoryItem;
        $this->inventoryItemId = $inventoryItem->id;
    }

    /**
     * @return InventoryLocation
     */
    public function getInventoryLocation(): InventoryLocation
    {
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->inventoryLocationId);
    }

    /**
     * @return Purchasable
     */
    public function getPurchasable(): Purchasable
    {
        return $this->getInventoryItem()->getPurchasable();
    }
}
