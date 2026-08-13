<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderAdjustmentNotFoundException;
use craft\commerce\Plugin;
use craft\events\RegisterComponentTypesEvent;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Commerce\Order\Records\OrderAdjustment as OrderAdjustmentRecord;
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
            $record = OrderAdjustmentRecord::find($orderAdjustment->id);

            if (!$record) {
                throw new OrderAdjustmentNotFoundException('Order Adjustment with ID "' . $orderAdjustment->id . '" not found!');
            }
        }

        if ($runValidation && !$orderAdjustment->validate()) {
            Log::info('Order Adjustment not saved due to validation error(s).');
            return false;
        }

        $record->name = $orderAdjustment->name;
        $record->type = $orderAdjustment->type;
        $record->description = $orderAdjustment->description;
        $record->amount = $orderAdjustment->amount;
        $record->included = $orderAdjustment->included;
        $record->sourceSnapshot = $orderAdjustment->getSourceSnapshot();
        $record->lineItemId = $orderAdjustment->getLineItem()->id ?? null;
        $record->orderId = $orderAdjustment->getOrder()->id ?? null;
        $record->isEstimated = $orderAdjustment->isEstimated;

        $record->save();

        // Update the model with the latest IDs
        $orderAdjustment->id = $record->id;
        $orderAdjustment->orderId = $record->orderId;
        $orderAdjustment->lineItemId = $record->lineItemId;

        return true;
    }

    /**
     * Delete all adjustments belonging to an order by its ID.
     */
    public function deleteAllOrderAdjustmentsByOrderId(int $orderId): bool
    {
        return (bool)OrderAdjustmentRecord::where('orderId', $orderId)->delete();
    }

    /**
     * Delete an order adjustment by its ID.
     */
    public function deleteOrderAdjustmentByAdjustmentId(int $adjustmentId): bool
    {
        $orderAdjustment = OrderAdjustmentRecord::find($adjustmentId);

        if (!$orderAdjustment) {
            return false;
        }

        return (bool)$orderAdjustment->delete();
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
