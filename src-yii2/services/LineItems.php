<?php

namespace craft\commerce\services;

use craft\commerce\Plugin;
use CraftCms\Commerce\Order\Events\LineItemCreated;
use CraftCms\Commerce\Order\Events\LineItemPopulated;
use CraftCms\Commerce\Order\Events\LineItemSaved;
use CraftCms\Commerce\Order\Events\LineItemSaving;
use Illuminate\Support\Facades\Event;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\LineItem\LineItems::class)` instead.
 */
class LineItems extends Component
{
    public const EVENT_BEFORE_SAVE_LINE_ITEM = \CraftCms\Commerce\Order\LineItem\LineItems::EVENT_BEFORE_SAVE_LINE_ITEM;

    public const EVENT_AFTER_SAVE_LINE_ITEM = \CraftCms\Commerce\Order\LineItem\LineItems::EVENT_AFTER_SAVE_LINE_ITEM;

    public const EVENT_CREATE_LINE_ITEM = \CraftCms\Commerce\Order\LineItem\LineItems::EVENT_CREATE_LINE_ITEM;

    public const EVENT_POPULATE_LINE_ITEM = \CraftCms\Commerce\Order\LineItem\LineItems::EVENT_POPULATE_LINE_ITEM;

    /**
     * @return LineItem[]
     */
    public function getAllLineItemsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->getAllLineItemsByOrderId($orderId);
    }

    public function resolveLineItem(Order $order, int $purchasableId, array $options = [], array $params = []): LineItem
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->resolveLineItem($order, $purchasableId, $options, $params);
    }

    public function resolveCustomLineItem(Order $order, string $sku, array $options = []): LineItem
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->resolveCustomLineItem($order, $sku, $options);
    }

    public function saveLineItem(LineItem $lineItem, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->saveLineItem($lineItem, $runValidation);
    }

    public function getLineItemById(int $id): ?LineItem
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->getLineItemById($id);
    }

    public function create(Order $order, array $params = [], LineItemType $type = LineItemType::Purchasable): LineItem
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->create($order, $params, $type);
    }

    public function deleteAllLineItemsByOrderId(int $orderId): bool
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->deleteAllLineItemsByOrderId($orderId);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadLineItemsForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->eagerLoadLineItemsForOrders($orders);
    }

    public function orderCompleteHandler(LineItem $lineItem, Order $order): void
    {
        app(\CraftCms\Commerce\Order\LineItem\LineItems::class)->orderCompleteHandler($lineItem, $order);
    }

    public static function registerEvents(): void
    {
        Event::listen(LineItemSaving::class, static function(LineItemSaving $event) {
            $legacy = Plugin::getInstance()->getLineItems();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_SAVE_LINE_ITEM)) {
                $legacy->trigger(self::EVENT_BEFORE_SAVE_LINE_ITEM, $event);
            }
        });

        Event::listen(LineItemSaved::class, static function(LineItemSaved $event) {
            $legacy = Plugin::getInstance()->getLineItems();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_SAVE_LINE_ITEM)) {
                $legacy->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, $event);
            }
        });

        Event::listen(LineItemCreated::class, static function(LineItemCreated $event) {
            $legacy = Plugin::getInstance()->getLineItems();
            if ($legacy->hasEventHandlers(self::EVENT_CREATE_LINE_ITEM)) {
                $legacy->trigger(self::EVENT_CREATE_LINE_ITEM, $event);
            }
        });

        Event::listen(LineItemPopulated::class, static function(LineItemPopulated $event) {
            $legacy = Plugin::getInstance()->getLineItems();
            if ($legacy->hasEventHandlers(self::EVENT_POPULATE_LINE_ITEM)) {
                $legacy->trigger(self::EVENT_POPULATE_LINE_ITEM, $event);
            }
        });
    }
}
