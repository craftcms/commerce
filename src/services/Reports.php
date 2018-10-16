<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use League\Csv\Writer;
use yii\base\Component;

/**
 * Reports service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Reports extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get a order summary CSV file for date range and an optional status.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $orderStatusId Status ID, or null for all statuses
     * @return Writer|string
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\HttpException
     * @throws \yii\web\RangeNotSatisfiableHttpException
     */
    public function getOrdersCsv($startDate, $endDate, $orderStatusId = null)
    {
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
            'totalPrice',
            'totalPaid',
            'paidStatus',
            'isCompleted',
            'dateOrdered',
            'datePaid',
            'currency',
            'paymentCurrency',
            'lastIp',
            'orderlanguage',
            'message',
            'shippingMethodHandle',
        ];

        // Dont use `date(dateOrdered)` in sql to force comparison to whole day, instead just remove timestamp and shift end date.
        $startDate = new \DateTime($startDate);
        $startDate->setTime(0,0);
        $endDate = new \DateTime($endDate);
        $endDate->modify('+1 day'); //so that we capture whole day of endDate

        $orderQuery = (new Query())
            ->select($columns)
            ->from('{{%commerce_orders}}')
            ->andWhere('isCompleted = true')
            ->andWhere(['>=', 'dateOrdered', Db::prepareDateForDb($startDate)])
            ->andWhere(['<=', 'dateOrdered', Db::prepareDateForDb($endDate)]);

        $status = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);
        if ($status) {
            $orderQuery->andWhere('orderStatusId = :id', [':id' => $status->id]);
        }

        $orders = $orderQuery->all();
        $csv = Writer::createFromString('');
        $csv->insertOne($columns);
        $csv->insertAll($orders);
        $csv = $csv->getContent();

        return $csv;
    }

}
