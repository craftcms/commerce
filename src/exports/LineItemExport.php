<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\exports;

use craft\base\ElementExporter;
use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\db\Query as CraftQuery;
use craft\elements\db\ElementQueryInterface;

class LineItemExport extends ElementExporter
{
    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Plugin::t('Line Items');
    }

    /**
     * @inheritDoc
     */
    public function export(ElementQueryInterface $query): array
    {
        $orderIds = $query->ids();

        $columns = [
            'lineitems.id',
            'lineitems.orderId',
            'lineitems.purchasableId',
            'lineitems.taxCategoryId',
            'lineitems.lineItemStatusId',
            'lineitems.shippingCategoryId',
            'lineitems.options',
            'lineitems.optionsSignature',
            'lineitems.price',
            'lineitems.saleAmount',
            'lineitems.salePrice',
            'lineitems.qty',
            'lineitems.subtotal',
            'totalTax' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS . ' adjustments')
                ->where(['and','[[adjustments.orderId]] = [[lineitems.orderId]]','[[adjustments.lineItemId]] = [[lineitems.id]]' ])
                ->andWhere(['type' => Tax::ADJUSTMENT_TYPE])
                ->andWhere(['included' => 0]),
            'totalTaxIncluded' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS . ' adjustments')
                ->where(['and','[[adjustments.orderId]] = [[lineitems.orderId]]','[[lineItemId]] = [[lineitems.id]]' ])
                ->andWhere(['type' => Tax::ADJUSTMENT_TYPE])
                ->andWhere(['included' => 1]),
            'totalShipping' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS . ' adjustments')
                ->where(['and','[[adjustments.orderId]] = [[lineitems.orderId]]','[[lineItemId]] = [[lineitems.id]]' ])
                ->andWhere(['type' => Shipping::ADJUSTMENT_TYPE]),
            'totalDiscount' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS . ' adjustments')
                ->where(['and','[[adjustments.orderId]] = [[lineitems.orderId]]','[[adjustments.lineItemId]] = [[lineitems.id]]' ])
                ->andWhere(['type' => Discount::ADJUSTMENT_TYPE]),
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
        ];

        $lineItems = (new CraftQuery())
            ->select($columns)
            ->from(Table::LINEITEMS . ' lineitems')
            ->leftJoin(Table::ORDERS . ' orders', '[[lineitems.orderId]] = [[orders.id]]')
            ->where(['[[lineitems.orderId]]' => $orderIds])
            ->all();

        return $lineItems;
    }
}