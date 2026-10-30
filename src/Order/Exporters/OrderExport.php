<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Exporters;

use CraftCms\Cms\Element\Exporters\ElementExporter;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Adjuster\Shipping;
use CraftCms\Commerce\Order\Adjuster\Tax;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;

class OrderExport extends ElementExporter
{
    #[\Override]
    public static function displayName(): string
    {
        return t('Orders (Legacy)', category: 'commerce');
    }

    #[\Override]
    public function export(ElementQueryInterface $query): mixed
    {
        $orderIds = $query->ids();

        return DB::table(Table::ORDERS)
            ->select([
                'id',
                'number',
                'email',
                'gatewayId',
                'paymentSourceId',
                'customerId',
                'orderStatusId',
                'couponCode',
                'itemTotal',
                'totalPrice',
                'totalPaid',
                'paidStatus',
                'isCompleted',
                'dateOrdered',
                'datePaid',
                'currency',
                'paymentCurrency',
                'lastIp',
                'orderLanguage',
                'message',
                'shippingMethodHandle',
            ])
            ->selectSub($this->orderAdjustmentTotal(Tax::ADJUSTMENT_TYPE)->where('included', 0), 'totalTax')
            ->selectSub($this->orderAdjustmentTotal(Tax::ADJUSTMENT_TYPE)->where('included', 1), 'totalTaxIncluded')
            ->selectSub($this->orderAdjustmentTotal(Shipping::ADJUSTMENT_TYPE), 'totalShipping')
            ->selectSub($this->orderAdjustmentTotal(Discount::ADJUSTMENT_TYPE), 'totalDiscount')
            ->whereIn('id', $orderIds)
            ->get()
            ->map(fn($row) => (array)$row)
            ->all();
    }

    private function orderAdjustmentTotal(string $adjustmentType): Builder
    {
        return DB::table(Table::ORDERADJUSTMENTS)
            ->selectRaw('SUM(amount)')
            ->whereColumn('orderId', Table::ORDERS . '.id')
            ->where('type', $adjustmentType);
    }
}
