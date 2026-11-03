<?php

namespace craft\commerce\services;

use craft\commerce\events\DiscountAdjustmentsEvent;
use CraftCms\Commerce\Order\Adjuster\AdjusterTypes;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsCreated;
use CraftCms\Yii2Adapter\Event\TypeRegistryCompatibility;
use Illuminate\Support\Facades\Event;
use yii\base\Component;
use yii\base\Event as YiiEvent;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\OrderAdjustments::class)` instead.
 */
class OrderAdjustments extends Component
{
    /** @deprecated in 6.0.0. Use `app(\CraftCms\Commerce\Order\Adjuster\AdjusterTypes::class)->register()` instead. */
    public const EVENT_REGISTER_ORDER_ADJUSTERS = 'registerOrderAdjusters';

    /** @deprecated in 6.0.0. Use `app(\CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes::class)->register()` instead. */
    public const EVENT_REGISTER_DISCOUNT_ADJUSTERS = 'registerDiscountAdjusters';

    /**
     * @return class-string<AdjusterInterface>[]
     */
    public function getAdjusters(): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getAdjusters();
    }

    public function getOrderAdjustmentById(int $id): ?OrderAdjustment
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getOrderAdjustmentById($id);
    }

    /**
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getAllOrderAdjustmentsByOrderId($orderId);
    }

    public function saveOrderAdjustment(OrderAdjustment $orderAdjustment, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->saveOrderAdjustment($orderAdjustment, $runValidation);
    }

    public function deleteAllOrderAdjustmentsByOrderId(int $orderId): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->deleteAllOrderAdjustmentsByOrderId($orderId);
    }

    public function deleteOrderAdjustmentByAdjustmentId(int $adjustmentId): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->deleteOrderAdjustmentByAdjustmentId($adjustmentId);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadOrderAdjustmentsForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->eagerLoadOrderAdjustmentsForOrders($orders);
    }

    /**
     * @return class-string<AdjusterInterface>[]
     */
    public function getDiscountAdjusters(): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getDiscountAdjusters();
    }

    /** @internal */
    /**
     * Bridges the discount adjuster's Laravel event to the legacy `Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED` Yii event.
     */
    public static function registerEvents(): void
    {
        Event::listen(DiscountAdjustmentsCreated::class, static function(DiscountAdjustmentsCreated $event) {
            $legacyEvent = new DiscountAdjustmentsEvent([
                'order' => $event->order,
                'discount' => $event->discount,
                'adjustments' => $event->adjustments,
            ]);

            // Yii matches class handlers by name, so handlers on the alias and on the new class are triggered separately.
            foreach ([\craft\commerce\adjusters\Discount::class, Discount::class] as $class) {
                if (!$legacyEvent->handled && YiiEvent::hasHandlers($class, Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED)) {
                    YiiEvent::trigger($class, Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, $legacyEvent);
                }
            }

            $event->adjustments = $legacyEvent->adjustments;
            $event->isValid = $event->isValid && $legacyEvent->isValid;
        });
    }

    public static function finalizeRegistrationEvents(): void
    {
        $plugin = \craft\commerce\Plugin::getInstance()->getOrderAdjustments();

        TypeRegistryCompatibility::reconcile(app(AdjusterTypes::class), $plugin, self::EVENT_REGISTER_ORDER_ADJUSTERS);
        TypeRegistryCompatibility::reconcile(app(DiscountAdjusterTypes::class), $plugin, self::EVENT_REGISTER_DISCOUNT_ADJUSTERS);
    }
}
