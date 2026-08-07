<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderAdjustmentNotFoundException;
use craft\commerce\Plugin;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\events\RegisterComponentTypesEvent;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Cms\Support\Json;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Singleton]
class OrderAdjustments
{
    public const string EVENT_REGISTER_ORDER_ADJUSTERS = 'registerOrderAdjusters';

    public const string EVENT_REGISTER_DISCOUNT_ADJUSTERS = 'registerDiscountAdjusters';

    /**
     * Get all order adjusters.
     *
     * @return class-string<AdjusterInterface>[]
     */
    public function getAdjusters(): array
    {
        $adjusters = [];

        $adjusters[] = Shipping::class;

        foreach ($this->getDiscountAdjusters() as $discountAdjuster) {
            $adjusters[] = $discountAdjuster;
        }

        $taxEngine = Plugin::getInstance()->getTaxes()->getEngine();
        $adjusters[] = $taxEngine->taxAdjusterClass();

        $event = new RegisterComponentTypesEvent(['types' => $adjusters]);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getOrderAdjustments()->hasEventHandlers(self::EVENT_REGISTER_ORDER_ADJUSTERS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getOrderAdjustments()->trigger(self::EVENT_REGISTER_ORDER_ADJUSTERS, $event);
        }

        return $event->types;
    }

    public function getOrderAdjustmentById(int $id): ?OrderAdjustment
    {
        $row = $this->query()->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $row = (array)$row;
        $row['sourceSnapshot'] = Json::decodeIfJson($row['sourceSnapshot']);

        return new OrderAdjustment($row);
    }

    /**
     * Get all order adjustments by order's ID.
     *
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId(int $orderId): array
    {
        return $this->query()
            ->where('orderId', $orderId)
            ->get()
            ->map(function($row) {
                $row = (array)$row;
                $row['sourceSnapshot'] = Json::decodeIfJson($row['sourceSnapshot']);

                return new OrderAdjustment($row);
            })
            ->all();
    }

    /**
     * Save an order adjustment.
     */
    public function saveOrderAdjustment(OrderAdjustment $orderAdjustment, bool $runValidation = true): bool
    {
        $newAdjustment = !$orderAdjustment->id;

        if ($newAdjustment) {
            $record = new OrderAdjustmentRecord();
        } else {
            /** @phpstan-ignore-next-line */
            $record = OrderAdjustmentRecord::findOne($orderAdjustment->id);

            if (!$record) {
                throw new OrderAdjustmentNotFoundException('Order Adjustment with ID "' . $orderAdjustment->id . '" not found!');
            }
        }

        if ($runValidation && !$orderAdjustment->validate()) {
            Log::info('Order Adjustment not saved due to validation error(s).');
            return false;
        }

        /** @phpstan-ignore-next-line */
        $record->name = $orderAdjustment->name;
        /** @phpstan-ignore-next-line */
        $record->type = $orderAdjustment->type;
        /** @phpstan-ignore-next-line */
        $record->description = $orderAdjustment->description;
        /** @phpstan-ignore-next-line */
        $record->amount = $orderAdjustment->amount;
        /** @phpstan-ignore-next-line */
        $record->included = $orderAdjustment->included;
        /** @phpstan-ignore-next-line */
        $record->sourceSnapshot = $orderAdjustment->getSourceSnapshot();
        /** @phpstan-ignore-next-line */
        $record->lineItemId = $orderAdjustment->getLineItem()->id ?? null;
        /** @phpstan-ignore-next-line */
        $record->orderId = $orderAdjustment->getOrder()->id ?? null;
        /** @phpstan-ignore-next-line */
        $record->isEstimated = $orderAdjustment->isEstimated;

        /** @phpstan-ignore-next-line */
        $record->save(false);

        // Update the model with the latest IDs
        /** @phpstan-ignore-next-line */
        $orderAdjustment->id = $record->id;
        /** @phpstan-ignore-next-line */
        $orderAdjustment->orderId = $record->orderId;
        /** @phpstan-ignore-next-line */
        $orderAdjustment->lineItemId = $record->lineItemId;

        return true;
    }

    /**
     * Delete all adjustments belonging to an order by its ID.
     */
    public function deleteAllOrderAdjustmentsByOrderId(int $orderId): bool
    {
        /** @phpstan-ignore-next-line */
        return (bool)OrderAdjustmentRecord::deleteAll(['orderId' => $orderId]);
    }

    /**
     * Delete an order adjustment by its ID.
     */
    public function deleteOrderAdjustmentByAdjustmentId(int $adjustmentId): bool
    {
        /** @phpstan-ignore-next-line */
        $orderAdjustment = OrderAdjustmentRecord::findOne($adjustmentId);

        if (!$orderAdjustment) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return $orderAdjustment->delete();
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadOrderAdjustmentsForOrders(array $orders): array
    {
        $orderIds = collect($orders)->pluck('id')->filter()->all();
        $orderAdjustmentResults = $this->query()->whereIn('orderId', $orderIds)->get();

        $orderAdjustments = [];

        foreach ($orderAdjustmentResults as $result) {
            $result = (array)$result;
            $result['sourceSnapshot'] = Json::decodeIfJson($result['sourceSnapshot']);
            $adjustment = new OrderAdjustment($result);

            $orderAdjustments[$adjustment->orderId] ??= [];
            $orderAdjustments[$adjustment->orderId][] = $adjustment;
        }

        foreach ($orders as $key => $order) {
            if (isset($orderAdjustments[$order->id])) {
                $order->setAdjustments($orderAdjustments[$order->id]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * @return class-string<AdjusterInterface>[]
     */
    public function getDiscountAdjusters(): array
    {
        $discountEvent = new RegisterComponentTypesEvent(['types' => []]);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getOrderAdjustments()->hasEventHandlers(self::EVENT_REGISTER_DISCOUNT_ADJUSTERS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getOrderAdjustments()->trigger(self::EVENT_REGISTER_DISCOUNT_ADJUSTERS, $discountEvent);
        }

        $discountEvent->types[] = Discount::class;

        return $discountEvent->types;
    }

    private function query(): Builder
    {
        return DB::table(Table::ORDERADJUSTMENTS)
            ->select([
                'amount',
                'description',
                'id',
                'included',
                'isEstimated',
                'lineItemId',
                'name',
                'orderId',
                'sourceSnapshot',
                'type',
            ]);
    }
}
