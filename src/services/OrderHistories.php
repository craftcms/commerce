<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\OrderHistory as OrderHistoryRecord;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\helpers\DateTimeHelper;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Order history service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderHistories extends Component
{
    /**
     * @event OrderStatusEvent The event that is triggered when an order status is changed.
     *
     * Plugins can get notified when an order status is changed
     *
     * ```php
     * use craft\commerce\events\OrderStatusEvent;
     * use craft\commerce\services\OrderHistories;
     * use craft\commerce\models\OrderHistory;
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrderHistories::class,
     *     OrderHistories::EVENT_ORDER_STATUS_CHANGE,
     *     function(OrderStatusEvent $event) {
     *         // @var OrderHistory $orderHistory
     *         $orderHistory = $event->orderHistory;
     *         // @var Order $order
     *         $order = $event->order;
     *
     *         // Let the delivery department know the order’s ready to be delivered
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_ORDER_STATUS_CHANGE = 'orderStatusChange';

    /**
     * Get order history by its ID.
     */
    public function getOrderHistoryById(int $id): ?OrderHistory
    {
        $result = $this->_createOrderHistoryQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new OrderHistory($result) : null;
    }

    /**
     * Get all order histories by an order ID.
     *
     * @param int $id orderId
     * @return OrderHistory[]
     */
    public function getAllOrderHistoriesByOrderId(int $id): array
    {
        $rows = $this->_createOrderHistoryQuery()
            ->where(['orderId' => $id])
            ->orderBy('dateCreated desc, id desc')
            ->all();

        $histories = [];

        foreach ($rows as $row) {
            $histories[] = new OrderHistory($row);
        }

        return $histories;
    }

    /**
     * Create an order history from an order.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws MissingComponentException
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
        if (!Craft::$app->request->isConsoleRequest
            && !Craft::$app->getResponse()->isSent
            && (Craft::$app->getSession()->getHasSessionId() || Craft::$app->getSession()->getIsActive())
            && $currentUser = Craft::$app->getUser()->getIdentity()
        ) {
            $userId = $currentUser->id;
        }

        if ($userId) {
            $user = Craft::$app->getUsers()->getUserById($userId);
            if($user) {
                $orderHistoryModel->userId = $userId;
                $orderHistoryModel->userName = $user->fullName ?? $user->getEmail();
            }else{
                $orderHistoryModel->userName = $order->getEmail();
            }
        }

        $orderHistoryModel->message = $order->message;

        if (!$this->saveOrderHistory($orderHistoryModel)) {
            return false;
        }

        Plugin::getInstance()->getOrderStatuses()->statusChangeHandler($order, $orderHistoryModel);

        // Raising 'orderStatusChange' event
        if ($this->hasEventHandlers(self::EVENT_ORDER_STATUS_CHANGE)) {
            $this->trigger(self::EVENT_ORDER_STATUS_CHANGE, new OrderStatusEvent([
                'orderHistory' => $orderHistoryModel,
                'order' => $order,
            ]));
        }

        return true;
    }

    /**
     * Save an order history.
     *
     * @param bool $runValidation Whether the Order Adjustment should be validated
     * @throws Exception
     */
    public function saveOrderHistory(OrderHistory $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = OrderHistoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No order history exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderHistoryRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Order history not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->message = $model->message;
        $record->newStatusId = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->userId = $model->userId;
        $record->userName = $model->userName;
        $record->orderId = $model->orderId;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);

        return true;
    }

    /**
     * Delete an order history by its ID.
     *
     * @throws Throwable
     * @throws StaleObjectException
     * @noinspection PhpUnused
     */
    public function deleteOrderHistoryById(int $id): bool
    {
        $orderHistory = OrderHistoryRecord::findOne($id);

        if ($orderHistory) {
            return (bool)$orderHistory->delete();
        }

        return false;
    }


    /**
     * Returns a Query object prepped for retrieving Order History.
     *
     * @return Query The query object.
     */
    private function _createOrderHistoryQuery(): Query
    {
        return (new Query())
            ->select([
                'userId',
                'dateCreated',
                'id',
                'message',
                'newStatusId',
                'orderId',
                'prevStatusId',
            ])
            ->from([Table::ORDERHISTORIES]);
    }
}
