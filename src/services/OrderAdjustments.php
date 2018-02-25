<?php

namespace craft\commerce\services;

use Craft;
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
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderAdjustments extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent This event is raised when compiling the list of adjusters for an order
     *
     * Plugins can register their own adjusters.
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\OrderAdjustments;
     * use yii\base\Event;
     *
     * Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {
     *     $e->types[] = MyAdjuster::class;
     * });
     * ```
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
     * @param OrderAdjustment $orderAdjustment
     * @param bool $runValidation Whether the Order Adjustment should be validated
     * @return bool
     * @throws Exception
     */
    public function saveOrderAdjustment(OrderAdjustment $orderAdjustment, bool $runValidation = true): bool
    {

        $isNewOrderAdjustment = !$orderAdjustment->id;

        if ($orderAdjustment->id) {
            $record = OrderAdjustmentRecord::findOne($orderAdjustment->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No order Adjustment exists with the ID “{id}”',
                    ['id' => $orderAdjustment->id]));
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
            $record->$field = $orderAdjustment->$field;
        }

        if ($runValidation && !$orderAdjustment->validate()) {
            Craft::info('Order Adjustment not saved due to validation error.', __METHOD__);
            return false;
        }

        $record->save(false);

        // Now that we have an ID, save it on the model
        if ($isNewOrderAdjustment) {
            $orderAdjustment->id = $record->id;
        }

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param int $orderId
     * @return bool
     */
    public function deleteAllOrderAdjustmentsByOrderId($orderId): bool
    {
        return OrderAdjustmentRecord::deleteAll(['orderId' => $orderId]);
    }

    /**
     * @param int $adjustmentId
     * @return bool
     */
    public function deleteOrderAdjustmentByAdjustmentId($adjustmentId): bool
    {
        $orderAdjustment = OrderAdjustmentRecord::findOne($adjustmentId);

        if (!$orderAdjustment) {
            return false;
        }

        return $orderAdjustment->delete();
    }

    // Private Methods
    // =========================================================================

    /**
     * @param OrderAdjustmentRecord $record
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
