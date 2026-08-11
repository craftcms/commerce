<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Services\Inventory;
use CraftCms\Commerce\Services\InventoryLocations;
use CraftCms\Commerce\Services\LineItems;
use DateTime;

class InventoryTransaction extends Component
{
    public int $inventoryItemId;

    public int $inventoryLocationId;

    public int $quantity;

    public string $type;

    public ?int $lineItemId = null;

    public ?int $transferId = null;

    public string $movementHash = '';

    public string $note = '';

    public ?int $userId = null;

    public ?DateTime $dateCreated = null;

    public function getInventoryItem(): InventoryItem
    {
        return app(Inventory::class)->getInventoryItemById($this->inventoryItemId);
    }

    public function getInventoryLocation(): InventoryLocation
    {
        return app(InventoryLocations::class)->getInventoryLocationById($this->inventoryLocationId);
    }

    public function getPurchasable(): PurchasableInterface
    {
        return $this->getInventoryItem()->getPurchasable();
    }

    public function getLineItem(): ?LineItem
    {
        if ($this->lineItemId === null) {
            return null;
        }

        return app(LineItems::class)->getLineItemById($this->lineItemId);
    }

    public function getOrder(): ?Order
    {
        if (!$this->getLineItem()) {
            return null;
        }

        /** @var ?Order $order */
        $order = Order::find()->id($this->getLineItem()->orderId)->status(null)->one();

        return $order;
    }

    public function getUser(): ?User
    {
        if (!$this->userId) {
            return null;
        }

        return Users::getUserById($this->userId);
    }
}
