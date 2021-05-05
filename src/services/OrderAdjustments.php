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
use craft\commerce\base\AdjusterInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\db\Query;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\ArrayHelper;
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
     * @event RegisterComponentTypesEvent The event that is triggered for registration of additional adjusters.
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\OrderAdjustments;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrderAdjustments::class,
     *     OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->types[] = MyAdjuster::class;
     *     }
     * );
     * ```
     */
    const EVENT_REGISTER_ORDER_ADJUSTERS = 'registerOrderAdjusters';

    /**
     * @event RegisterComponentTypesEvent The event that is triggered for registration of additional adjusters.
     * @since 3.1.9
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\OrderAdjustments;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrderAdjustments::class,
     *     OrderAdjustments::EVENT_REGISTER_DISCOUNT_ADJUSTERS,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->types[] = MyDiscountAdjuster::class;
     *     }
     * );
     * ```
     */
    const EVENT_REGISTER_DISCOUNT_ADJUSTERS = 'registerDiscountAdjusters';


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

        foreach ($this->getDiscountAdjusters() as $discountAdjuster) {
            $adjusters[] = $discountAdjuster;
        }

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE, '>=')) {
            $engine = Plugin::getInstance()->getTaxes()->getEngine();
            $adjusters[] = $engine->taxAdjusterClass();
        }

        $event = new RegisterComponentTypesEvent([
            'types' => $adjusters
        ]);

        if (Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
            if ($this->hasEventHandlers(self::EVENT_REGISTER_ORDER_ADJUSTERS)) {
                $this->trigger(self::EVENT_REGISTER_ORDER_ADJUSTERS, $event);
            }
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
            $adjustment = new OrderAdjustment($row);
            $adjustment->typecastAttributes();
            $adjustments[] = $adjustment;
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
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadOrderAdjustmentsForOrders(array $orders): array
    {
        $orderIds = ArrayHelper::getColumn($orders, 'id');
        $orderAdjustmentResults = $this->_createOrderAdjustmentQuery()->andWhere(['orderId' => $orderIds])->all();

        $orderAdjustments = [];

        foreach ($orderAdjustmentResults as $result) {
            $result['sourceSnapshot'] = Json::decodeIfJson($result['sourceSnapshot']);
            $adjustment = new OrderAdjustment($result);
            $adjustment->typecastAttributes();
            $orderAdjustments[$adjustment->orderId] = $orderAdjustments[$adjustment->orderId] ?? [];
            $orderAdjustments[$adjustment->orderId][] = $adjustment;
        }

        foreach ($orders as $key => $order) {
            if (isset($orderAdjustments[$order->id])) {
                $order->setAdjustments($orderAdjustments[$order->id]);
                $orders[$key] = $order;
            }
        }

        return $orders;
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

    /**
     * @return array
     */
    public function getDiscountAdjusters(): array
    {
        $discountEvent = new RegisterComponentTypesEvent([
            'types' => []
        ]);

        if (Plugin::getInstance()->is(Plugin::EDITION_PRO)) {

            if ($this->hasEventHandlers(self::EVENT_REGISTER_DISCOUNT_ADJUSTERS)) {
                $this->trigger(self::EVENT_REGISTER_DISCOUNT_ADJUSTERS, $discountEvent);
            }
            $discountEvent->types[] = Discount::class;
        }

        return $discountEvent->types;
    }
}
