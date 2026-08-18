<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Adjuster\AdjusterTypes;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Commerce\Order\Records\OrderAdjustment as OrderAdjustmentRecord;
use CraftCms\Commerce\Tax\Taxes;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Singleton]
class OrderAdjustments
{
    /**
     * Get all order adjusters.
     *
     * @return class-string<AdjusterInterface>[]
     */
    public function getAdjusters(): array
    {
        $adjusters = app(AdjusterTypes::class)->types()->all();

        foreach ($this->getDiscountAdjusters() as $discountAdjuster) {
            $adjusters[] = $discountAdjuster;
        }

        $taxEngine = app(Taxes::class)->getEngine();
        $adjusters[] = $taxEngine->taxAdjusterClass();

        return array_values(array_unique($adjusters));
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
        return app(DiscountAdjusterTypes::class)->types()->all();
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
