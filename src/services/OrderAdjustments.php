<?php

namespace craft\commerce\services;

use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order adjustment service.
 *
 * @property array|AdjusterInterface[] $adjusters
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 *
 */
class OrderAdjustments extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event AdjusterEvent This event is raised when compiling the list of adjusters for an order
     */
    const EVENT_REGISTER_ORDER_ADJUSTERS = 'registerOrderAdjusters';

    // Public Methods
    // =========================================================================

    /**
     * @return AdjusterInterface[]
     */
    public function getAdjusters(): array
    {
        $adjusters = [
            Shipping::class,
            Discount::class,
            Tax::class
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $adjusters
        ]);
        $this->trigger(self::EVENT_REGISTER_ORDER_ADJUSTERS, $event);

        return $event->types;
    }

    /**
     * @param int $orderId
     *
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId($orderId): array
    {
        $records = OrderAdjustmentRecord::find()
            ->where(['orderId' => $orderId])
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
                throw new Exception(\Craft::t('commerce', 'No order Adjustment exists with the ID “{id}”',
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
            'lineItemId',
            'sourceSnapshot'
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
     * @param int $adjustmentId
     *
     * @return bool
     */
    public function deleteOrderAdjustmentByAdjustmentId($adjustmentId): bool
    {
        $orderAdjustment = OrderAdjustmentRecord::findOne($adjustmentId);

        if(!$orderAdjustment)
        {
            return false;
        }

        return $orderAdjustment->delete();
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
            'sourceSnapshot',
            'lineItemId',
            'orderId'
        ]));
    }
}
