<?php

namespace craft\commerce\services;

use craft\commerce\models\OrderAdjustment;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order adjustment service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderAdjustments extends Component
{
    /**
     * @param int $orderId
     *
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId($orderId): array
    {
        $records = OrderAdjustmentRecord::find()
            ->where('orderId = :orderId', [':orderId' => $orderId])
            ->all();

        return ArrayHelper::map($records, 'id', function($record) {
            return $this->_createOrderAdjustmentFromOrderAdjustmentRecord($record);
        });
    }

    /**
     * @param OrderAdjustment $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveOrderAdjustment(OrderAdjustment $model): bool
    {
        if ($model->id) {
            $record = OrderAdjustmentRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(\Craft::t('commerce', 'commerce', 'No order Adjustment exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new OrderAdjustmentRecord();
        }

        $fields = [
            'name',
            'type',
            'description',
            'amount',
            'included',
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
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param int $orderId
     *
     * @return bool
     */
    public function deleteAllOrderAdjustmentsByOrderId($orderId): bool
    {
        return OrderAdjustmentRecord::deleteAll(['orderId' => $orderId]);
    }

    /**
     * @param OrderAdjustmentRecord $record
     *
     * @return OrderAdjustment
     */
    private function _createOrderAdjustmentFromOrderAdjustmentRecord(OrderAdjustmentRecord $record): OrderAdjustment
    {
        return new OrderAdjustment($record->toArray([
            'id',
            'name',
            'description',
            'type',
            'amount',
            'included',
            'optionsJson',
            'orderId'
        ]));
    }
}
