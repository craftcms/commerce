<?php

namespace craft\commerce\services;

use craft\commerce\base\LegacyElementEvents;
use craft\commerce\events\AddLineItemEvent;
use craft\commerce\events\LineItemEvent;
use craft\commerce\events\OrderLineItemsRefreshEvent;
use craft\commerce\events\OrderNoticeEvent;
use craft\elements\User;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\LineItemAddApplied;
use CraftCms\Commerce\Order\Events\LineItemAdded;
use CraftCms\Commerce\Order\Events\LineItemAdding;
use CraftCms\Commerce\Order\Events\LineItemRemoveApplied;
use CraftCms\Commerce\Order\Events\LineItemRemoved;
use CraftCms\Commerce\Order\Events\LineItemsRefreshed;
use CraftCms\Commerce\Order\Events\LineItemsRefreshing;
use CraftCms\Commerce\Order\Events\OrderAuthorized;
use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\Commerce\Order\Events\OrderCompleting;
use CraftCms\Commerce\Order\Events\OrderNoticeSaving;
use CraftCms\Commerce\Order\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\Orders::class)` instead.
 */
class Orders extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = \CraftCms\Commerce\Order\Orders::CONFIG_FIELDLAYOUT_KEY;

    /**
     * Bridges the order's Laravel events to the legacy `Order::EVENT_*` Yii events.
     */
    public static function registerEvents(): void
    {
        $legacyOrder = \craft\commerce\elements\Order::class;

        Event::listen(OrderPaid::class, static function(OrderPaid $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_ORDER_PAID);
        });

        Event::listen(OrderAuthorized::class, static function(OrderAuthorized $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_ORDER_AUTHORIZED);
        });

        Event::listen(OrderCompleting::class, static function(OrderCompleting $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_BEFORE_COMPLETE_ORDER);
        });

        Event::listen(OrderCompleted::class, static function(OrderCompleted $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_COMPLETE_ORDER);
        });

        Event::listen(LineItemAdding::class, static function(LineItemAdding $event) use ($legacyOrder) {
            $legacyEvent = LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_BEFORE_ADD_LINE_ITEM, new AddLineItemEvent([
                'lineItem' => $event->lineItem,
                'isNew' => true,
            ]));

            if (!$legacyEvent->isValid) {
                $event->isValid = false;
            }
        });

        Event::listen(LineItemAdded::class, static function(LineItemAdded $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_ADD_LINE_ITEM, new LineItemEvent([
                'lineItem' => $event->lineItem,
                'isNew' => $event->isNew,
            ]));
        });

        Event::listen(LineItemRemoved::class, static function(LineItemRemoved $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_REMOVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $event->lineItem,
            ]));
        });

        Event::listen(LineItemAddApplied::class, static function(LineItemAddApplied $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_APPLY_ADD_LINE_ITEM, new LineItemEvent([
                'lineItem' => $event->lineItem,
                'isNew' => true,
            ]));
        });

        Event::listen(LineItemRemoveApplied::class, static function(LineItemRemoveApplied $event) use ($legacyOrder) {
            LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $event->lineItem,
            ]));
        });

        Event::listen(LineItemsRefreshing::class, static function(LineItemsRefreshing $event) use ($legacyOrder) {
            $legacyEvent = LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED, new OrderLineItemsRefreshEvent([
                'lineItems' => $event->lineItems,
                'recalculate' => $event->recalculate,
            ]));

            $event->lineItems = $legacyEvent->lineItems;
            $event->recalculate = $legacyEvent->recalculate;
        });

        Event::listen(LineItemsRefreshed::class, static function(LineItemsRefreshed $event) use ($legacyOrder) {
            $legacyEvent = LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_AFTER_LINE_ITEMS_REFRESHED, new OrderLineItemsRefreshEvent([
                'lineItems' => $event->lineItems,
                'recalculate' => $event->recalculate,
            ]));

            $event->lineItems = $legacyEvent->lineItems;
            $event->recalculate = $legacyEvent->recalculate;
        });

        Event::listen(OrderNoticeSaving::class, static function(OrderNoticeSaving $event) use ($legacyOrder) {
            $legacyEvent = LegacyElementEvents::trigger($event->order, $legacyOrder, Order::EVENT_BEFORE_APPLY_ADD_NOTICE, new OrderNoticeEvent([
                'orderNotice' => $event->orderNotice,
            ]));

            if (!$legacyEvent->isValid) {
                $event->isValid = false;
            }
        });
    }

    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\Orders::class)->handleChangedFieldLayout(new \CraftCms\Cms\ProjectConfig\Events\ItemUpdated($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    public function handleDeletedFieldLayout(): void
    {
        app(\CraftCms\Commerce\Order\Orders::class)->handleDeletedFieldLayout();
    }

    public function getOrderById(int $id): ?Order
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrderById($id);
    }

    public function getOrderByNumber(string $number): ?Order
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrderByNumber($number);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByCustomer(User|int $customer): ?array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrdersByCustomer($customer);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrdersByEmail($email);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->eagerLoadAddressesForOrders($orders);
    }

    /**
     * @param int|int[] $oldUserId
     */
    public function reassignOrders(int|array $oldUserId, int $newUserId): int
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->reassignOrders($oldUserId, $newUserId);
    }

    /**
     * @param int|int[] $orderIds
     */
    public function removeCustomerData(int|array $orderIds, array $dataToRemove = ['customerId', 'email']): int
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->removeCustomerData($orderIds, $dataToRemove);
    }
}
