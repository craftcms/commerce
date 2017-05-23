<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\helpers\Db;
use craft\commerce\models\Email;
use craft\commerce\models\OrderStatus;
use craft\commerce\records\Email as EmailRecord;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\commerce\records\OrderStatusEmail as OrderStatusEmailRecord;
use yii\base\Component;
use Craft;

/**
 * Order status service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderStatuses extends Component
{

    /**
     * @param string $handle
     *
     * @return OrderStatus|null
     */
    public function getOrderStatusByHandle($handle)
    {
        $result = OrderStatusRecord::find()->where(['handle' => $handle])->one();

        if ($result) {
            return new OrderStatus($result);
        }

        return null;
    }

    /**
     * @param $id
     *
     * @return Email[]
     */
    public function getAllEmailsByOrderStatusId($id)
    {
        $orderStatus = OrderStatusRecord::find()->with('emails')->where(['id' => $id])->one();

        if ($orderStatus) {
            return Email::populateModels($orderStatus->emails);
        }

        return [];
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
        $result = OrderStatusRecord::find()->where(['default' => true])->one();

        if ($result) {
            return new OrderStatus($result);
        }

        return null;
    }

    /**
     * @param OrderStatus $model
     * @param array       $emailIds
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveOrderStatus(OrderStatus $model, array $emailIds)
    {
        if ($model->id) {
            $record = OrderStatusRecord::findOne($model->id);
            if (!$record->id) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order status exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderStatusRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->color = $model->color;
        $record->sortOrder = $model->sortOrder ? $model->sortOrder : 999;
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
            Db::beginStackedTransaction();
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

                    foreach ($records as $record)
                    {
                        $record->delete();
                    }
                }

                //Save new links
                $rows = array_map(function($id) use ($record) {
                    return [$id, $record->id];
                }, $emailIds);
                $cols = ['emailId', 'orderStatusId'];
                $table = OrderStatusEmailRecord::tableName();
                Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows);

                // Now that we have a calendar ID, save it on the model
                $model->id = $record->id;

                Db::commitStackedTransaction();
            } catch (\Exception $e) {
                Db::rollbackStackedTransaction();
                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int
     *
     * @return bool
     */
    public function deleteOrderStatusById($id)
    {
        $statuses = $this->getAllOrderStatuses();

        $query = Order::find();
        $query->orderStatusId($id);
        $order = $query->one();

        if ($order) {
            return false;
        }

        if (count($statuses) >= 2) {

            $models = OrderStatusRecord::find()->where('id = :id',[':id' => $id])->all();
            foreach ($models as $model) {
                $model->delete();
            }

            return true;
        }

        return false;
    }

    /**
     *
     * @return OrderStatus[]
     */
    public function getAllOrderStatuses(): array
    {
        $orderStatusRecords = OrderStatusRecord::find()->orderBy('sortOrder')->all();

        return OrderStatus::populateModels($orderStatusRecords);
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
            $status = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($order->orderStatusId);
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
        $result = OrderStatusRecord::findOne($id);

        if ($result) {
            return new OrderStatus($result);
        }

        return null;
    }

    /**
     * @param $ids
     *
     * @return bool
     */
    public function reorderOrderStatuses($ids)
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update('commerce_orderstatuses',
                ['sortOrder' => $sortOrder + 1], ['id' => $id]);
        }

        return true;
    }
}
