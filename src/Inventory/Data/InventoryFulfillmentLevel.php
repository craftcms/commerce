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

    public ?int $orderId = null;

    public int $committedQuantity;

    public int $outstandingCommittedQuantity;

    public int $fulfilledQuantity;

    private ?int $_fulfillableQuantity = null;

    private ?Order $_order = null;

    private ?LineItem $_lineItem = null;

    /**
     * Returns the quantity of the inventory item that can be fulfilled from the inventory location, regardless of order.
     */
    public function getFulfillableQuantity(): int
    {
        return $this->_fulfillableQuantity ??= max(app(Inventory::class)->getFulfillableQuantity($this->inventoryItemId, $this->inventoryLocationId), 0);
    }

    /**
     * Returns the maximum quantity of the line item that can be fulfilled from the inventory location right now: its
     * outstanding committed quantity, limited by the stock that can be fulfilled from the location.
     */
    public function getMaxFulfillQuantity(): int
    {
        return min(max($this->outstandingCommittedQuantity, 0), $this->getFulfillableQuantity());
    }

    /**
     * Returns whether the inventory location doesn't have enough stock to fulfill the line item's outstanding committed quantity.
     */
    public function getIsShortOfStock(): bool
    {
        return $this->getMaxFulfillQuantity() < $this->outstandingCommittedQuantity;
    }

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

        if ($this->_lineItem === null && $this->_order !== null) {
            $this->_lineItem = collect($this->_order->getLineItems())->firstWhere('id', $this->lineItemId);
        }

        return $this->_lineItem ??= app(LineItems::class)->getLineItemById($this->lineItemId);
    }

    public function getOrder(): Order
    {
        /** @var Order $order */
        $order = $this->_order ??= Order::find()->id($this->orderId ?? $this->getLineItem()->orderId)->status(null)->one();
        return $order;
    }

    /**
     * Sets the order, e.g. when eager-loading the orders for a list of fulfillment levels.
     */
    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    public function getPurchasable(null|string|int $siteId = null): PurchasableInterface
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
