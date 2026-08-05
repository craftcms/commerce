<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Services\Inventory;
use CraftCms\Commerce\Services\InventoryLocations;

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

        // TODO: migrate to app(LineItems::class)->getLineItemById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
    }

    public function getOrder(): Order
    {
        /** @var Order $order */
        $order = Order::find()->id($this->getLineItem()->orderId)->status(null)->one();
        return $order;
    }

    public function getPurchasable(null|string|int $siteId = null): Purchasable
    {
        return $this->getInventoryItem()->getPurchasable($siteId);
    }
}
