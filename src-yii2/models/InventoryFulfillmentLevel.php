<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Inventory Fulfillment Level model
 * @since 5.0
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
     * @var int
     */
    public int $lineItemId;

    /**
     * @var int
     */
    public int $committedQuantity;

    /**
     * @var int
     */
    public int $outstandingCommittedQuantity;

    /**
     * @var int
     */
    public int $fulfilledQuantity;

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

    public function getOrder(): Order
    {
        return Order::find()->id($this->getLineItem()->order)->status(null)->one();
    }

    public function getLineItem(): LineItem
    {
        if (!$this->lineItemId) {
            throw new InvalidConfigException('InventoryFulfillmentLevel is not associated with a line item');
        }

        return Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
    }

    /**
     * @return Purchasable
     */
    public function getPurchasable(null|string|int $siteId = null): Purchasable
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
