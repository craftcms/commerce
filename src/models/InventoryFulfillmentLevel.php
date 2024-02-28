<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Inventory Fulfillment Level model
 */
class InventoryFulfillmentLevel extends Model
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
