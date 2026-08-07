<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\OrderNotices::class)` instead.
 */
class OrderNotices extends Component
{
    /**
     * @param Order[] $orders
     * @return Order[]
     * @throws InvalidConfigException
     */
    public function eagerLoadOrderNoticesForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Services\OrderNotices::class)->eagerLoadOrderNoticesForOrders($orders);
    }
}
