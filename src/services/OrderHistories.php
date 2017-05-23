<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\OrderHistory as OrderHistoryRecord;
use yii\base\Component;

/**
 * Order history service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderHistories extends Component
{
    /**
     * @param int $id
     *
     * @return OrderHistory|null
     */
    public function getOrderHistoryById($id)
    {
        $result = OrderHistoryRecord::findOne($id);

        if ($result) {
            return new OrderHistory($result);
        }

        return null;
    }

    /**
     * @param int $id orderId
     *
     * @return OrderHistory[]
     */
    public function getAllOrderHistoriesByOrderId($id)
    {
        $results = OrderHistoryRecord::find()->where(['orderId' => $id])->orderBy('dateCreated')->all();

        return OrderHistory::populateModels($results);
    }

    /**
     * @param Order $order
     * @param int   $oldStatusId
     *
     * @return bool
     * @throws Exception
     */
    public function createOrderHistoryFromOrder(Order $order, $oldStatusId)
    {
        $orderHistoryModel = new OrderHistory();
        $orderHistoryModel->orderId = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId = $order->orderStatusId;
        $orderHistoryModel->customerId = Craft::$app->request->isConsoleRequest ? $order->customerId : Plugin::getInstance()->getCustomers()->getCustomerId();
        $orderHistoryModel->message = $order->message;

        if (!$this->saveOrderHistory($orderHistoryModel)) {
            return false;
        }

        Plugin::getInstance()->getOrderStatuses()->statusChangeHandler($order, $orderHistoryModel);

        //raising event on status change
        $event = new Event($this, [
            'orderHistory' => $orderHistoryModel,
            'order' => $order
        ]);
        $this->onStatusChange($event);

        return true;
    }

    /**
     * @param OrderHistory $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveOrderHistory(OrderHistory $model)
    {
        if ($model->id) {
            $record = OrderHistoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order history exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderHistoryRecord();
        }

        $record->message = $model->message;
        $record->newStatusId = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->customerId = $model->customerId;
        $record->orderId = $model->orderId;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;
            $model->dateCreated = $record->dateCreated;

            return true;
        } else {
            return false;
        }
    }

    /**
     * Event method
     * Event params: order (Order), orderHistoryModel
     * (OrderHistory)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onStatusChange(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Order)) {
            throw new Exception('onStatusChange event requires "order" param with OrderModel instance');
        }

        if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof OrderHistory)) {
            throw new Exception('onStatusChange event requires "orderHistory" param with OrderHistoryModel instance');
        }

        $this->raiseEvent('onStatusChange', $event);
    }

    /**
     * @param $id
     *
     * @return bool|int
     */
    public function deleteOrderHistoryById($id)
    {
        $orderHistory = OrderHistoryRecord::findOne($id);

        if ($orderHistory) {
            return $orderHistory->delete();
        }
    }
}
