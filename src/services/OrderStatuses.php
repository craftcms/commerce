<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\DefaultOrderStatusEvent;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\commerce\records\OrderStatusEmail as OrderStatusEmailRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order status service.
 *
 * @property OrderStatus|null $defaultOrderStatus default order status from the DB
 * @property OrderStatus[]|array $allOrderStatuses all Order Statuses
 * @property null|int $defaultOrderStatusId default order status ID from the DB
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatuses extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event DefaultOrderStatusEvent The event that is triggered when getting a default status for an order.
     * You may set [[DefaultOrderStatusEvent::orderStatus]] to a desired OrderStatus to override the default status set in CP
     *
     * Plugins can get notified when a default order status is being fetched
     *
     * ```php
     * use craft\commerce\events\DefaultOrderStatusEvent;
     * use craft\commerce\services\OrderStatuses;
     * use yii\base\Event;
     *
     * Event::on(OrderStatuses::class, OrderStatuses::EVENT_DEFAULT_ORDER_STATUS, function(DefaultOrderStatusEvent $e) {
     *     // Do something - perhaps figure out a better default order statues than the one set in CP
     * });
     * ```
     */
    const EVENT_DEFAULT_ORDER_STATUS = 'defaultOrderStatus';


    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllStatuses = false;

    /**
     * @var OrderStatus[]
     */
    private $_orderStatusesById = [];

    /**
     * @var OrderStatus[]
     */
    private $_orderStatusesByHandle = [];

    /**
     * @var OrderStatus
     */
    private $_defaultOrderStatus;

    // Public Methods
    // =========================================================================

    /**
     * Get order status by its handle.
     *
     * @param string $handle
     * @return OrderStatus|null
     */
    public function getOrderStatusByHandle($handle)
    {
        if (isset($this->_orderStatusesByHandle[$handle])) {
            return $this->_orderStatusesByHandle[$handle];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeOrderStatus(new OrderStatus($result));

        return $this->_orderStatusesByHandle[$handle];
    }

    /**
     * Get default order status ID from the DB
     *
     * @return int|null
     */
    public function getDefaultOrderStatusId()
    {
        $defaultStatus = $this->getDefaultOrderStatus();

        if ($defaultStatus && $defaultStatus->id) {
            return $defaultStatus->id;
        }

        return null;
    }

    /**
     * Get default order status from the DB
     *
     * @return OrderStatus|null
     */
    public function getDefaultOrderStatus()
    {
        if ($this->_defaultOrderStatus !== null) {
            return $this->_defaultOrderStatus;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['default' => 1])
            ->one();

        return new OrderStatus($result);
    }

    /**
     * Get the default order status for a particular order. Defaults to the CP configured default order status.
     *
     * @param Order $order
     * @return OrderStatus|null
     */
    public function getDefaultOrderStatusForOrder(Order $order)
    {
        $orderStatus = $this->getDefaultOrderStatus();

        $event = new DefaultOrderStatusEvent();
        $event->orderStatus = $orderStatus;
        $event->order = $order;

        $this->trigger(self::EVENT_DEFAULT_ORDER_STATUS, $event);

        return $event->orderStatus;
    }

    /**
     * Save the order status.
     *
     * @param OrderStatus $model
     * @param array $emailIds
     * @param bool $runValidation should we validate this order status before saving.
     * @return bool
     * @throws Exception
     */
    public function saveOrderStatus(OrderStatus $model, array $emailIds, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = OrderStatusRecord::findOne($model->id);
            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No order status exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderStatusRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Order status not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->color = $model->color;
        $record->sortOrder = $model->sortOrder ?: 999;
        $record->default = $model->default;

        //validating emails ids
        $exist = EmailRecord::find()->where(['in', 'id', $emailIds])->exists();
        $hasEmails = (boolean)count($emailIds);

        if (!$exist && $hasEmails) {
            $model->addError('emails', 'One or more emails do not exist in the system.');
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Save it!
            $record->save(false);

            if ($record->default) {
                OrderStatusRecord::updateAll(['default' => 0], ['not', ['id' => $record->id]]);
            }

            //Delete old links
            if ($model->id) {
                $orderStatusEmailRecords = OrderStatusEmailRecord::find()->where(['orderStatusId' => $model->id])->all();

                foreach ($orderStatusEmailRecords as $orderStatusEmailRecord) {
                    $orderStatusEmailRecord->delete();
                }
            }

            //Save new links
            $rows = array_map(
                function($id) use ($record) {
                    return [$id, $record->id];
                }, $emailIds);

            $cols = ['emailId', 'orderStatusId'];
            $table = OrderStatusEmailRecord::tableName();
            Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

            // Now that we have an ID, save it on the model
            $model->id = $record->id;

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Delete an order status by ID
     *
     * @param $id
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteOrderStatusById($id): bool
    {
        $statuses = $this->getAllOrderStatuses();

        $existingOrder = Order::find()
            ->orderStatusId($id)
            ->one();

        // Not if it's still in use.
        if ($existingOrder) {
            return false;
        }

        if (\count($statuses) >= 2) {
            $record = OrderStatusRecord::findOne($id);

            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * Returns all Order Statuses
     *
     * @return OrderStatus[]
     */
    public function getAllOrderStatuses(): array
    {
        if (!$this->_fetchedAllStatuses) {
            $results = $this->_createOrderStatusesQuery()->all();

            foreach ($results as $row) {
                $this->_memoizeOrderStatus(new OrderStatus($row));
            }

            $this->_fetchedAllStatuses = true;
        }

        return $this->_orderStatusesById;
    }

    /**
     * Handler for order status change event
     *
     * @param Order $order
     * @param OrderHistory $orderHistory
     */
    public function statusChangeHandler($order, $orderHistory)
    {
        if ($order->orderStatusId) {
            $status = $this->getOrderStatusById($order->orderStatusId);
            if ($status && \count($status->emails)) {
                foreach ($status->emails as $email) {
                    Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory);
                }
            }
        }
    }

    /**
     * Get an order status by ID
     *
     * @param int $id
     * @return OrderStatus|null
     */
    public function getOrderStatusById($id)
    {
        if (isset($this->_orderStatusesById[$id])) {
            return $this->_orderStatusesById[$id];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeOrderStatus(new OrderStatus($result));

        return $this->_orderStatusesById[$id];
    }

    /**
     * Reorders the order statuses.
     *
     * @param array $ids
     * @return bool
     * @throws \yii\db\Exception
     */
    public function reorderOrderStatuses(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%commerce_orderstatuses}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize an order status  by its ID and handle.
     *
     * @param OrderStatus $orderStatus
     */
    private function _memoizeOrderStatus(OrderStatus $orderStatus)
    {
        $this->_orderStatusesById[$orderStatus->id] = $orderStatus;
        $this->_orderStatusesByHandle[$orderStatus->handle] = $orderStatus;
    }

    /**
     * Returns a Query object prepped for retrieving order statuses
     *
     * @return Query
     */
    private function _createOrderStatusesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'color',
                'sortOrder',
                'default',
            ])
            ->orderBy('sortOrder')
            ->from(['{{%commerce_orderstatuses}}']);
    }
}
