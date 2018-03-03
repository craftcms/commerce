<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\db\Query;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order adjustment service.
 *
 * @property AdjusterInterface[] $adjusters
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
     * Get all order adjusters.
     *
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
     * Get all order adjustments by order's ID.
     *
     * @param int $orderId
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId($orderId): array
    {
        $rows = $this->_createOrderAdjustmentQuery()
            ->where(['orderId' => $orderId])
            ->all();

        $adjustments = [];

        foreach ($rows as $row) {
            $adjustments[] = new OrderAdjustment($row);
        }

        return $adjustments;
    }

    /**
     * Save an order adjustment.
     *
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

        if ($runValidation && !$orderAdjustment->validate()) {
            Craft::info('Order Adjustment not saved due to validation error.', __METHOD__);
            return false;
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
     * Delete all adjustments belonging to an order by its ID.
     *
     * @param int $orderId
     * @return bool
     */
    public function deleteAllOrderAdjustmentsByOrderId($orderId): bool
    {
        return OrderAdjustmentRecord::deleteAll(['orderId' => $orderId]);
    }

    /**
     * Delete an order adjustment by its ID.
     *
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
     * Returns a Query object prepped for retrieving Order Adjustment.
     *
     * @return Query The query object.
     */
    private function _createOrderAdjustmentQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'description',
                'type',
                'amount',
                'included',
                'sourceSnapshot',
                'lineItemId',
                'orderId'
            ])
            ->from(['{{%commerce_orderadjustments}}']);
    }
}
