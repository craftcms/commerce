<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Models\OrderNotice;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

#[Singleton]
class OrderNotices
{
    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadOrderNoticesForOrders(array $orders): array
    {
        $orderIds = collect($orders)->pluck('id')->filter()->all();

        $orderNoticeResults = DB::table(Table::ORDERNOTICES)
            ->select(['attribute', 'noticeType', 'id', 'message', 'orderId', 'type'])
            ->whereIn('orderId', $orderIds)
            ->get();

        $orderNotices = [];

        foreach ($orderNoticeResults as $result) {
            $notice = new OrderNotice((array)$result);

            $orderNotices[$notice->orderId] ??= [];
            $orderNotices[$notice->orderId][] = $notice;
        }

        foreach ($orders as $key => $order) {
            if (isset($orderNotices[$order->id])) {
                $order->addNotices($orderNotices[$order->id]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }
}
