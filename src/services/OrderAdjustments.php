<?php
namespace craft\commerce\services;

use craft\commerce\models\OrderAdjustment;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use yii\base\Component;

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
    public function getAllOrderAdjustmentsByOrderId($orderId)
    {
        $records = $this->_createOrderAdjustmentsQuery()
            ->where('oa.orderId = :orderId', [':orderId' => $orderId])
            ->queryAll();

        return OrderAdjustment::populateModels($records);
    }

    /**
     * Returns a DbCommand object prepped for retrieving order adjustments.
     *
     * @return DbCommand
     */
    private function _createOrderAdjustmentsQuery()
    {
        return Craft::$app->getDb()->createCommand()
            ->select('oa.id, oa.type, oa.name, oa.included, oa.description, oa.amount, oa.optionsJson, oa.orderId')
            ->from('commerce_orderadjustments oa')
            ->order('type');
    }

    /**
     * @param OrderAdjustment $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveOrderAdjustment(OrderAdjustment $model)
    {
        if ($model->id) {
            $record = OrderAdjustmentRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order Adjustment exists with the ID “{id}”',
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
        } else {
            return false;
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * @param int $orderId
     *
     * @return int
     */
    public function deleteAllOrderAdjustmentsByOrderId($orderId)
    {
        return OrderAdjustmentRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
    }
}
