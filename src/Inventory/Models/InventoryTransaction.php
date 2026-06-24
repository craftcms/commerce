<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
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
        // TODO: migrate to app(Inventory::class)->getInventoryItemById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
    }

    public function getInventoryLocation(): \craft\commerce\models\InventoryLocation
    {
        // TODO: migrate to app(InventoryLocations::class)->getInventoryLocationById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($this->inventoryLocationId);
    }

    public function getPurchasable(): Purchasable
    {
        return $this->getInventoryItem()->getPurchasable();
    }

    public function getLineItem(): ?LineItem
    {
        if ($this->lineItemId === null) {
            return null;
        }

        // TODO: migrate to app(LineItems::class)->getLineItemById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
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
