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
    const EVENT_ORDER_STATUS_CHANGE = 'orderStatusChange';

    /**
     * Get order history by its ID.
     *
     * @param int $id
     * @return OrderHistory|null
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
     * @param Order $order
     * @param int|null $oldStatusId
     * @return bool
     * @throws Exception
     * @throws MissingComponentException
     * @throws InvalidConfigException
     */
    public function createOrderHistoryFromOrder(Order $order, int $oldStatusId = null): bool
    {
        $orderHistoryModel = new OrderHistory();
        $orderHistoryModel->orderId = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId = $order->orderStatusId;

        // TODO refactor the method by which we store and work out who changed the order history #COM-51
        $customerId = $order->customerId;

        // Use to current customer's ID only if we aren't in a console request
        // and we currently have an active session
        if (!Craft::$app->request->isConsoleRequest && !Craft::$app->getResponse()->isSent && (Craft::$app->getSession()->getHasSessionId() || Craft::$app->getSession()->getIsActive())) {
            $customerId = Plugin::getInstance()->getCustomers()->getCustomer()->id;
        }

        $orderHistoryModel->customerId = $customerId;

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
     * @param OrderHistory $model
     * @param bool $runValidation Whether the Order Adjustment should be validated
     * @return bool
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
        $record->customerId = $model->customerId;
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
     * @param int $id
     * @return bool
     * @throws \Throwable
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
                'customerId',
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
