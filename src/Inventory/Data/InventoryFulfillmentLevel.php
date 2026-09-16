<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

class InventoryFulfillmentLevel extends Component
{
    public int $inventoryItemId;

    public int $inventoryLocationId;

    public int $lineItemId;

    public int $committedQuantity;

    public int $outstandingCommittedQuantity;

    public int $fulfilledQuantity;

    public function getInventoryItem(): InventoryItem
    {
        return app(Inventory::class)->getInventoryItemById($this->inventoryItemId);
    }

    public function getInventoryLocation(): InventoryLocation
    {
        return app(InventoryLocations::class)->getInventoryLocationById($this->inventoryLocationId);
    }

    public function getLineItem(): LineItem
    {
        if (!$this->lineItemId) {
            throw new \InvalidArgumentException('InventoryFulfillmentLevel is not associated with a line item');
        }

        return app(LineItems::class)->getLineItemById($this->lineItemId);
    }

    public function getOrder(): Order
    {
        /** @var Order $order */
        $order = Order::find()->id($this->getLineItem()->orderId)->status(null)->one();
        return $order;
    }

    public function getPurchasable(null|string|int $siteId = null): PurchasableInterface
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
