<?php /** @noinspection ArgumentEqualsDefaultValueInspection */
/** @noinspection ArgumentEqualsDefaultValueInspection */

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\events\ReportEvent;
use craft\commerce\Plugin;
use craft\db\Query as CraftQuery;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\base\Component;
use yii\base\Exception;
use yii\web\BadRequestHttpException;

/**
 * Reports service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Reports extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_GENERATE_EXPORT = 'beforeGenerateExport';

    // Public Methods
    // =========================================================================

    /**
     * Get a order summary CSV or XLS file for date range and an optional status.
     *
     * @param string $format The format, supports csv, xls
     * @param string $startDate
     * @param string $endDate
     * @param int|null $orderStatusId Status ID, or null for all statuses
     * @return string|null
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getOrdersExportFile($format, $startDate, $endDate, $orderStatusId = null)
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
            'orderLanguage',
            'message',
            'shippingMethodHandle',
        ];

        // Dont use `date(dateOrdered)` in sql to force comparison to whole day, instead just remove timestamp and shift end date.
        $startDate = new DateTime($startDate);
        $startDate->setTime(0, 0);
        $endDate = new DateTime($endDate);
        $endDate->modify('+1 day'); //so that we capture whole day of endDate

        $orderQuery = (new CraftQuery())
            ->select($columns)
            ->from(Table::ORDERS)
            ->andWhere('[[isCompleted]] = true')
            ->andWhere(['>=', 'dateOrdered', Db::prepareDateForDb($startDate)])
            ->andWhere(['<=', 'dateOrdered', Db::prepareDateForDb($endDate)]);

        $status = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);
        if ($status) {
            $orderQuery->andWhere('orderStatusId = :id', [':id' => $status->id]);
        }

        $orders = $orderQuery->all();

        // Raise the beforeGenerateExport event
        $event = new ReportEvent([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'status' => $status,
            'orderQuery' => $orderQuery,
            'columns' => $columns,
            'orders' => $orders,
            'format' => $format,
        ]);
        $this->trigger(self::EVENT_BEFORE_GENERATE_EXPORT, $event);

        // Populate the spreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->fromArray($event->columns, null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray($event->orders, null, 'A2');

        // Could use the writer factory with a $format <-> phpspreadsheet string map, but this is more simple for now.
        switch ($format) {
            case 'csv':
                $writer = new Csv($spreadsheet);
                break;
            case 'xls':
                $writer = new Xls($spreadsheet);
                break;
            case 'xlsx':
                $writer = new Xlsx($spreadsheet);
                break;
            case 'ods':
                $writer = new Ods($spreadsheet);
                break;
            default:
                throw new BadRequestHttpException('Invalid export format: ' . $format);
        }

        // Prepare and write temp file to disk
        $path = Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'commerce-order-exports';
        FileHelper::createDirectory($path);
        $filename = uniqid('orderexport', true) . '.' . $format;
        $tempFile = Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'commerce-order-exports' . DIRECTORY_SEPARATOR . $filename;
        if (($handle = fopen($tempFile, 'wb')) === false) {
            throw new Exception('Could not create temp file: ' . $tempFile);
        }
        fclose($handle);

        $writer->save($tempFile);

        return $tempFile;
    }
}
