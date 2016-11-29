<?php
namespace Craft;

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
class Commerce_OrderHistoriesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_OrderHistoryModel|null
     */
    public function getOrderHistoryById($id)
    {
        $result = Commerce_OrderHistoryRecord::model()->findById($id);

        if ($result) {
            return Commerce_OrderHistoryModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param int $id orderId
     *
     * @return Commerce_OrderHistoryModel[]
     */
    public function getAllOrderHistoriesByOrderId($id)
    {
        $results = Commerce_OrderHistoryRecord::model()->findAllByAttributes(['orderId'=>$id],['order'=>'dateCreated DESC']);

        return Commerce_OrderHistoryModel::populateModels($results);
    }

    /**
     * @param array $attr
     *
     * @return Commerce_OrderHistoryModel|null
     */
    public function getOrderHistoryByAttributes(array $attr)
    {
        $result = Commerce_OrderHistoryRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_OrderHistoryModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param \CDbCriteria|array $criteria
     *
     * @return Commerce_OrderHistoryModel[]
     */
    public function getAllOrderHistories(array $criteria = [])
    {
        $records = Commerce_OrderHistoryRecord::model()->findAll($criteria);

        return Commerce_OrderHistoryModel::populateModels($records);
    }

    /**
     * @param Commerce_OrderModel $order
     * @param int                 $oldStatusId
     *
     * @return bool
     * @throws Exception
     */
    public function createOrderHistoryFromOrder(Commerce_OrderModel $order, $oldStatusId)
    {
        $orderHistoryModel = new Commerce_OrderHistoryModel();
        $orderHistoryModel->orderId = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId = $order->orderStatusId;
        $orderHistoryModel->customerId = craft()->isConsole() ? $order->customerId : craft()->commerce_customers->getCustomerId();
        $orderHistoryModel->message = $order->message;

        if (!$this->saveOrderHistory($orderHistoryModel))
        {
            return false;
        }

        craft()->commerce_orderStatuses->statusChangeHandler($order, $orderHistoryModel);

        //raising event on status change
        $event = new Event($this, ['orderHistory' => $orderHistoryModel,
                                   'order'        => $order]);
        $this->onStatusChange($event);

        return true;
    }

    /**
     * @param Commerce_OrderHistoryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveOrderHistory(Commerce_OrderHistoryModel $model)
    {
        if ($model->id) {
            $record = Commerce_OrderHistoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No order history exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_OrderHistoryRecord();
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
     * Event params: order (Commerce_OrderModel), orderHistoryModel
     * (Commerce_OrderHistoryModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onStatusChange(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel)) {
            throw new Exception('onStatusChange event requires "order" param with OrderModel instance');
        }

        if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof Commerce_OrderHistoryModel)) {
            throw new Exception('onStatusChange event requires "orderHistory" param with OrderHistoryModel instance');
        }

        $this->raiseEvent('onStatusChange', $event);
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteOrderHistoryById($id)
    {
        Commerce_OrderHistoryRecord::model()->deleteByPk($id);
    }
}
