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

class LineItemExport extends ElementExporter
{
    #[\Override]
    public static function displayName(): string
    {
        return t('Line Items', category: 'commerce');
    }

    #[\Override]
    public function export(ElementQueryInterface $query): mixed
    {
        $orderIds = $query->ids();

        return DB::table(Table::LINEITEMS . ' as lineitems')
            ->select([
                'lineitems.id',
                'lineitems.orderId',
                'lineitems.purchasableId',
                'lineitems.description',
                'lineitems.sku',
                'lineitems.taxCategoryId',
                'lineitems.lineItemStatusId',
                'lineitems.shippingCategoryId',
                'lineitems.options',
                'lineitems.optionsSignature',
                'lineitems.price',
                'lineitems.promotionalAmount',
                'lineitems.salePrice',
                'lineitems.qty',
                'lineitems.subtotal',
                'lineitems.total',
                'lineitems.weight',
                'lineitems.height',
                'lineitems.length',
                'lineitems.width',
                'lineitems.note',
                'lineitems.privateNote',
                'lineitems.snapshot',
                'lineitems.dateCreated',
                'lineitems.dateUpdated',
                'lineitems.uid',
            ])
            ->selectSub($this->lineItemAdjustmentTotal(Tax::ADJUSTMENT_TYPE)->where('included', 0), 'totalTax')
            ->selectSub($this->lineItemAdjustmentTotal(Tax::ADJUSTMENT_TYPE)->where('included', 1), 'totalTaxIncluded')
            ->selectSub($this->lineItemAdjustmentTotal(Shipping::ADJUSTMENT_TYPE), 'totalShipping')
            ->selectSub($this->lineItemAdjustmentTotal(Discount::ADJUSTMENT_TYPE), 'totalDiscount')
            ->leftJoin(Table::ORDERS . ' as orders', 'lineitems.orderId', '=', 'orders.id')
            ->whereIn('lineitems.orderId', $orderIds)
            ->get()
            ->map(fn($row) => (array)$row)
            ->all();
    }

    private function lineItemAdjustmentTotal(string $adjustmentType): Builder
    {
        return DB::table(Table::ORDERADJUSTMENTS . ' as adjustments')
            ->selectRaw('SUM(amount)')
            ->whereColumn('adjustments.orderId', 'lineitems.orderId')
            ->whereColumn('adjustments.lineItemId', 'lineitems.id')
            ->where('type', $adjustmentType);
    }
}
