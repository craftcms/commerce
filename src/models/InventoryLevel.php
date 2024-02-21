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
     * @param InventoryMovementType $type
     * @return int
     */
    public function getTotal(InventoryMovementType $type): int
    {
        return $this->{$type->key . 'Total'};
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
        return Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
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
