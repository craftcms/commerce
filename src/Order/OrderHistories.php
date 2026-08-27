<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use craft\commerce\Plugin;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\OrderStatusEvent;
use CraftCms\Commerce\Order\Models\OrderHistory as OrderHistoryRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

#[Singleton]
class OrderHistories
{
    public const string EVENT_ORDER_STATUS_CHANGE = 'orderStatusChange';

    /**
     * Get order history by its ID.
     */
    public function getOrderHistoryById(int $id): ?OrderHistory
    {
        $result = $this->query()->where('id', $id)->first();

        return $result ? new OrderHistory((array)$result) : null;
    }

    /**
     * Get all order histories by an order ID.
     *
     * @return OrderHistory[]
     */
    public function getAllOrderHistoriesByOrderId(int $id): array
    {
        return $this->query()
            ->where('orderId', $id)
            ->orderBy('dateCreated', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($row) => new OrderHistory((array)$row))
            ->all();
    }

    /**
     * Create an order history from an order.
     */
    public function createOrderHistoryFromOrder(Order $order, ?int $oldStatusId): bool
    {
        $orderHistoryModel = new OrderHistory();
        $orderHistoryModel->orderId = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId = $order->orderStatusId;

        // By default the user who changed the status is the same as the user who placed the order
        $userId = $order->getCustomerId();

        // If the user is logged in, use the current user
        if (!app()->runningInConsole()
            && session()->isStarted()
            && $currentUser = currentUserElement()
        ) {
            $userId = $currentUser->id;
        }

        if ($userId) {
            $user = Users::getUserById($userId);
            if ($user) {
                $orderHistoryModel->userId = $userId;
                $orderHistoryModel->userName = $user->fullName ?? $user->email;
            } else {
                $orderHistoryModel->userName = $order->getEmail();
            }
        }

        $orderHistoryModel->message = $order->message;

        if (!$this->saveOrderHistory($orderHistoryModel)) {
            return false;
        }

        app(OrderStatuses::class)->statusChangeHandler($order, $orderHistoryModel);

        // Raising 'orderStatusChange' event
        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getOrderHistories()->hasEventHandlers(self::EVENT_ORDER_STATUS_CHANGE)) {
            $event = new OrderStatusEvent(
                orderHistory: $orderHistoryModel,
                order: $order,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getOrderHistories()->trigger(self::EVENT_ORDER_STATUS_CHANGE, $event);
        }

        return true;
    }

    /**
     * Save an order history.
     */
    public function saveOrderHistory(OrderHistory $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = OrderHistoryRecord::find($model->id);

            if (!$record) {
                throw new \RuntimeException(t('No order history exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new OrderHistoryRecord();
        }

        if ($runValidation && !$model->validate()) {
            Log::info('Order history not saved due to validation error.');

            return false;
        }

        $record->message = $model->message;
        $record->newStatusId = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->userId = $model->userId;
        $record->userName = $model->userName;
        $record->orderId = $model->orderId;

        $record->save();

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;
        /** @phpstan-ignore-next-line */
        $model->dateCreated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateCreated);

        return true;
    }

    /**
     * Delete an order history by its ID.
     */
    public function deleteOrderHistoryById(int $id): bool
    {
        $orderHistory = OrderHistoryRecord::find($id);

        if ($orderHistory) {
            return (bool)$orderHistory->delete();
        }

        return false;
    }

    private function query(): Builder
    {
        return DB::table(Table::ORDERHISTORIES)
            ->select([
                'userId',
                'dateCreated',
                'id',
                'message',
                'newStatusId',
                'orderId',
                'prevStatusId',
            ]);
    }
}
