<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Component;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderNotice;
use craft\db\Query;
use craft\helpers\ArrayHelper;

/**
 * Order adjustment service.
 *
 * @property AdjusterInterface[] $adjusters
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrderNotices extends Component
{
    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.x
     */
    public function eagerLoadOrderNoticesForOrders(array $orders): array
    {
        $orderIds = ArrayHelper::getColumn($orders, 'id');
        $orderNoticesResults = $this->_createOrderNoticeQuery()->andWhere(['orderId' => $orderIds])->all();
        $orderNotices = [];

        foreach ($orderNoticesResults as $result) {

            /** @var OrderNotice $notice */
            $notice = Craft::createObject([
                'class' => OrderNotice::class,
                'attributes' => $result
            ]);

            $orderNotices[$notice->orderId] = $orderNotices[$notice->orderId] ?? [];
            $orderNotices[$notice->orderId][] = $notice;
        }

        foreach ($orders as $key => $order) {
            /** @var Order $order */
            if (isset($orderNotices[$order->id])) {
                $order->addNotices($orderNotices[$order->id]);
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
    private function _createOrderNoticeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'orderId',
                'type',
                'attribute',
                'message'
            ])
            ->from([Table::ORDERNOTICES]);
    }
}
