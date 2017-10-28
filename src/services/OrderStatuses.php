<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 *
 * @property OrderStatus[]|array $allOrderStatuses
 * @property null|int            $defaultOrderStatusId
 * @property OrderStatus|null    $defaultOrderStatus
 */
class OrderStatuses extends Component
{
    /**
     * @var bool
     */
    private $_fetchedAllStatues = false;

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
     * @param string $handle
     *
     * @return OrderStatus|null
     */
    public function getOrderStatusByHandle($handle)
    {
        if (isset($this->_orderStatusesByHandle[$handle])) {
            return $this->_orderStatusesByHandle[$handle];
        }

        if ($this->_fetchedAllStatues) {
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
        if (null === $this->_defaultOrderStatus) {
            $row = $this->_createOrderStatusesQuery()
                ->where(['default' => 1])
                ->one();

            if (!$row) {
                return null;
            }

            $this->_defaultOrderStatus = new OrderStatus($row);
        }

        return $this->_defaultOrderStatus;
    }

    /**
     * @param OrderStatus $model
     * @param array       $emailIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveOrderStatus(OrderStatus $model, array $emailIds): bool
    {
        if ($model->id) {
            $record = OrderStatusRecord::findOne($model->id);
            if (!$record->id) {
                throw new Exception(Craft::t('commerce', 'No order status exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderStatusRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->color = $model->color;
        $record->sortOrder = $model->sortOrder ?: 999;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating emails ids
        $exist = EmailRecord::find()->where(['in', 'id', $emailIds])->exists();
        $hasEmails = (boolean)count($emailIds);

        if (!$exist && $hasEmails) {
            $model->addError('emails',
                'One or more emails do not exist in the system.');
        }

        //saving
        if (!$model->hasErrors()) {
            $db = Craft::$app->getDb();
            $transaction = $db->beginTransaction();

            try {
                //only one default status can be among statuses of one order type
                if ($record->default) {
                    OrderStatusRecord::updateAll(['default' => 0]);
                }

                // Save it!
                $record->save(false);

                //Delete old links
                if ($model->id) {
                    $records = OrderStatusEmailRecord::find()->where(['orderStatusId' => $model->id])->all();

                    foreach ($records as $record) {
                        $record->delete();
                    }
                }

                //Save new links
                $rows = array_map(function($id) use ($record) {
                    return [$id, $record->id];
                }, $emailIds);
                $cols = ['emailId', 'orderStatusId'];
                $table = OrderStatusEmailRecord::tableName();
                Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

                // Now that we have a calendar ID, save it on the model
                $model->id = $record->id;

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }

            return true;
        }

        return false;
    }

    /**
     * Delete an order status by id.
     *
     * @param int
     *
     * @return bool
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

        if (count($statuses) >= 2) {
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
        if (!$this->_fetchedAllStatues) {
            $results = $this->_createOrderStatusesQuery()->all();

            foreach ($results as $row) {
                $this->_memoizeOrderStatus(new OrderStatus($row));
            }

            $this->_fetchedAllStatues = true;
        }

        return $this->_orderStatusesById;
    }

    /**
     * Handler for order status change event
     *
     * @param Order        $order
     * @param OrderHistory $orderHistory
     *
     * @throws Exception
     */
    public function statusChangeHandler($order, $orderHistory)
    {
        if ($order->orderStatusId) {
            $status = $this->getOrderStatusById($order->orderStatusId);
            if ($status && count($status->emails)) {
                foreach ($status->emails as $email) {
                    Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory);
                }
            }
        }
    }

    /**
     * @param int $id
     *
     * @return OrderStatus|null
     */
    public function getOrderStatusById($id)
    {
        if (isset($this->_orderStatusesById[$id])) {
            return $this->_orderStatusesById[$id];
        }

        if ($this->_fetchedAllStatues) {
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
     * @param $ids
     *
     * @return bool
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
     *
     * @return void
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
