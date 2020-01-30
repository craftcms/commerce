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
use craft\commerce\db\Table;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\db\Query;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Json;
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


    /**
     * Get all order adjusters.
     *
     * @return string[]
     */
    public function getAdjusters(): array
    {
        $adjusters = [];

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE, '>=')) {
            $adjusters[] = Shipping::class;
        }

        if (Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            $adjusters[] = Discount::class;
        }

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE, '>=')) {
            $adjusters[] = Tax::class;
        }

        $event = new RegisterComponentTypesEvent([
            'types' => $adjusters
        ]);

        if (Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            $this->trigger(self::EVENT_REGISTER_ORDER_ADJUSTERS, $event);
        }

        return $event->types;
    }

    /**
     * @param int $id
     * @return OrderAdjustment|null
     */
    public function getOrderAdjustmentById(int $id)
    {
        $row = $this->_createOrderAdjustmentQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        $row['sourceSnapshot'] = Json::decodeIfJson($row['sourceSnapshot']);
        $adjustment = new OrderAdjustment($row);
        $adjustment->typecastAttributes();
        return $adjustment;
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
            $row['sourceSnapshot'] = Json::decodeIfJson($row['sourceSnapshot']);
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
                throw new Exception(Plugin::t( 'No order Adjustment exists with the ID “{id}”',
                    ['id' => $orderAdjustment->id]));
            }
        } else {
            $record = new OrderAdjustmentRecord();
        }

        if ($runValidation && !$orderAdjustment->validate()) {
            Craft::info('Order Adjustment not saved due to validation error.', __METHOD__);
            return false;
        }

        $record->name = $orderAdjustment->name;
        $record->type = $orderAdjustment->type;
        $record->description = $orderAdjustment->description;
        $record->amount = $orderAdjustment->amount;
        $record->included = $orderAdjustment->included;
        $record->sourceSnapshot = $orderAdjustment->sourceSnapshot;
        $record->lineItemId = $orderAdjustment->getLineItem()->id ?? null;
        $record->orderId = $orderAdjustment->getOrder()->id ?? null;
        $record->sourceSnapshot = $orderAdjustment->sourceSnapshot;
        $record->isEstimated = $orderAdjustment->isEstimated;

        $record->save(false);

        // Update the model with the latest IDs
        $orderAdjustment->id = $record->id;
        $orderAdjustment->orderId = $record->orderId;
        $orderAdjustment->lineItemId = $record->lineItemId;

        return true;
    }


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
                'orderId',
                'isEstimated'
            ])
            ->from([Table::ORDERADJUSTMENTS]);
    }
}
