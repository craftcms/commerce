<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\LineItems::class)` instead.
 */
class LineItems extends Component
{
    public const EVENT_BEFORE_SAVE_LINE_ITEM = \CraftCms\Commerce\Services\LineItems::EVENT_BEFORE_SAVE_LINE_ITEM;

    public const EVENT_AFTER_SAVE_LINE_ITEM = \CraftCms\Commerce\Services\LineItems::EVENT_AFTER_SAVE_LINE_ITEM;

    public const EVENT_CREATE_LINE_ITEM = \CraftCms\Commerce\Services\LineItems::EVENT_CREATE_LINE_ITEM;

    public const EVENT_POPULATE_LINE_ITEM = \CraftCms\Commerce\Services\LineItems::EVENT_POPULATE_LINE_ITEM;

    /**
     * @return LineItem[]
     */
    public function getAllLineItemsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->getAllLineItemsByOrderId($orderId);
    }

    public function resolveLineItem(Order $order, int $purchasableId, array $options = [], array $params = []): LineItem
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->resolveLineItem($order, $purchasableId, $options, $params);
    }

    public function resolveCustomLineItem(Order $order, string $sku, array $options = []): LineItem
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->resolveCustomLineItem($order, $sku, $options);
    }

    public function saveLineItem(LineItem $lineItem, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->saveLineItem($lineItem, $runValidation);
    }

    public function getLineItemById(int $id): ?LineItem
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->getLineItemById($id);
    }

    public function create(Order $order, array $params = [], LineItemType $type = LineItemType::Purchasable): LineItem
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->create($order, $params, $type);
    }

    public function deleteAllLineItemsByOrderId(int $orderId): bool
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->deleteAllLineItemsByOrderId($orderId);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadLineItemsForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Services\LineItems::class)->eagerLoadLineItemsForOrders($orders);
    }

    public function orderCompleteHandler(LineItem $lineItem, Order $order): void
    {
        app(\CraftCms\Commerce\Services\LineItems::class)->orderCompleteHandler($lineItem, $order);
    }
}
