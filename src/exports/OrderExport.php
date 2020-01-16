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

class OrderExport extends ElementExporter
{
    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Plugin::t('Orders (Legacy)');
    }

    /**
     * @inheritDoc
     */
    public function export(ElementQueryInterface $query): array
    {
        $orderIds = $query->ids();

        $columns = [
            'id',
            'number',
            'email',
            'gatewayId',
            'paymentSourceId',
            'customerId',
            'orderStatusId',
            'couponCode',
            'itemTotal',
            'totalTax' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS)
                ->where('[[orderId]] = ' . Table::ORDERS . '.[[id]]')
                ->andWhere(['type' => Tax::ADJUSTMENT_TYPE])
                ->andWhere(['included' => 0]),
            'totalTaxIncluded' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS)
                ->where('[[orderId]] = ' . Table::ORDERS . '.[[id]]')
                ->andWhere(['type' => Tax::ADJUSTMENT_TYPE])
                ->andWhere(['included' => 1]),
            'totalShipping' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS)
                ->where('[[orderId]] = ' . Table::ORDERS . '.[[id]]')
                ->andWhere(['type' => Shipping::ADJUSTMENT_TYPE]),
            'totalDiscount' => (new CraftQuery())
                ->select('SUM([[amount]])')
                ->from(Table::ORDERADJUSTMENTS)
                ->where('[[orderId]] = ' . Table::ORDERS . '.[[id]]')
                ->andWhere(['type' => Discount::ADJUSTMENT_TYPE]),
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
        ];

        $orders = (new CraftQuery())
            ->select($columns)
            ->from(Table::ORDERS)
            ->where(['id' => $orderIds])
            ->all();

        return $orders;
    }
}