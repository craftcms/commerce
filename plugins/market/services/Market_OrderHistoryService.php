<?php
namespace Craft;

/**
 * Class Market_OrderHistoryService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_OrderHistoryService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Market_OrderHistoryModel
     */
    public function getById($id)
    {
        $record = Market_OrderHistoryRecord::model()->findById($id);

        return Market_OrderHistoryModel::populateModel($record);
    }

    /**
     * @param array $attr
     *
     * @return Market_OrderHistoryModel
     */
    public function getByAttributes(array $attr)
    {
        $record = Market_OrderHistoryRecord::model()->findByAttributes($attr);

        return Market_OrderHistoryModel::populateModel($record);
    }

    /**
     * @param \CDbCriteria|array $criteria
     *
     * @return Market_OrderHistoryModel[]
     */
    public function getAll(array $criteria = [])
    {
        $records = Market_OrderHistoryRecord::model()->findAll($criteria);

        return Market_OrderHistoryModel::populateModels($records);
    }

    /**
     * @param Market_OrderModel $order
     * @param int               $oldStatusId
     *
     * @return bool
     * @throws Exception
     */
    public function createFromOrder(Market_OrderModel $order, $oldStatusId)
    {
        $orderHistoryModel               = new Market_OrderHistoryModel();
        $orderHistoryModel->orderId      = $order->id;
        $orderHistoryModel->prevStatusId = $oldStatusId;
        $orderHistoryModel->newStatusId  = $order->orderStatusId;
        $orderHistoryModel->customerId   = craft()->market_customer->getCustomerId();
        $orderHistoryModel->message      = $order->message;

        if (!$this->save($orderHistoryModel)) {
            return false;
        }

        //raising event on status change
        $event = new Event($this, [
            'orderHistory'      => $orderHistoryModel,
            'order'             => $order
        ]);
        $this->onStatusChange($event);

        return true;
    }

    /**
     * @param Market_OrderHistoryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Market_OrderHistoryModel $model)
    {
        if ($model->id) {
            $record = Market_OrderHistoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No order history exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Market_OrderHistoryRecord();
        }

        $record->message      = $model->message;
        $record->newStatusId  = $model->newStatusId;
        $record->prevStatusId = $model->prevStatusId;
        $record->customerId   = $model->customerId;
        $record->orderId      = $model->orderId;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * Event method
     * Event params: order (Market_OrderModel), orderHistoryModel
     * (Market_OrderHistoryModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onStatusChange(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Market_OrderModel)) {
            throw new Exception('onStatusChange event requires "order" param with OrderModel instance');
        }

        if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof Market_OrderHistoryModel)) {
            throw new Exception('onStatusChange event requires "orderHistory" param with OrderHistoryModel instance');
        }

        $this->raiseEvent('onStatusChange', $event);
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteById($id)
    {
        Market_OrderHistoryRecord::model()->deleteByPk($id);
    }
}