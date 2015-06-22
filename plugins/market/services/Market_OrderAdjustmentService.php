<?php

namespace Craft;

/**
 * Class Market_OrderAdjustmentService
 *
 * @package Craft
 */
class Market_OrderAdjustmentService extends BaseApplicationComponent
{
    /**
     * @param int $orderId
     *
     * @return Market_OrderAdjustmentModel[]
     */
    public function getAllByOrderId($orderId)
    {
        $records = Market_OrderAdjustmentRecord::model()->findAllByAttributes(['orderId' => $orderId]);

        return Market_OrderAdjustmentModel::populateModels($records);
    }

    /**
     * @param Market_OrderAdjustmentModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function save(Market_OrderAdjustmentModel $model)
    {
        if ($model->id) {
            $record = Market_OrderAdjustmentRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No order Adjustment exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Market_OrderAdjustmentRecord();
        }

        $fields = [
            'name',
            'type',
            'description',
            'amount',
            'orderId',
            'optionsJson'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }
        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            $record->save(false);
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $orderId
     *
     * @return int
     */
    public function deleteAllByOrderId($orderId)
    {
        return Market_OrderAdjustmentRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
    }
}