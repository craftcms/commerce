<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\elements\Transfer;
use craft\commerce\Plugin;
use craft\elements\User;
use DateTime;

/**
 * Inventory Item model
 */
class InventoryTransaction extends Model
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
     * @var int The quantity
     */
    public int $quantity;

    /**
     * @var string The type
     */
    public string $type;

    /**
     * @var ?int
     */
    public ?int $lineItemId = null;

    /**
     * @var ?int
     */
    public ?int $transferId = null;

    /**
     * @var string
     */
    public string $movementHash = '';

    /**
     * @var string
     */
    public string $note = '';

    /**
     * @var int|null
     */
    public ?int $userId = null;


    /**
     * @var ?DateTime
     */
    public ?\DateTime $dateCreated = null;

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

    /**
     * @return ?Order
     */
    public function getOrder(): ?Order
    {
        if (!$this->getLineItem()) {
            return null;
        }

        /** @var ?Order $order */
        $order = Order::find()->id($this->getLineItem()->orderId)->status(null)->one();

        return $order;
    }

    /**
     * @return ?LineItem
     */
    public function getLineItem(): ?LineItem
    {
        return Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
    }


    /**
     * @return ?Transfer
     */
    public function getTransfer(): ?Transfer
    {
        if (!$this->transferId) {
            return null;
        }

        /** @var ?Transfer $transfer */
        $transfer = Transfer::find()->id($this->transferId)->status(null)->one();

        return $transfer;
    }

    /**
     * @return ?User
     */
    public function getUser(): ?User
    {
        if (!$this->userId) {
            return null;
        }

        /** @var ?User $user */
        $user = User::find()->id($this->userId)->status(null)->one();

        return $user;
    }
}
