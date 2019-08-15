<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use HttpInvalidParamException;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\RangeNotSatisfiableHttpException;
use yii\web\Response;

/**
 * Class Downloads Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DownloadsController extends BaseFrontEndController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     * @throws HttpException
     * @throws Throwable
     * @throws Exception
     * @throws RangeNotSatisfiableHttpException
     */
    public function actionPdf(): Response
    {
        $number = Craft::$app->getRequest()->getQueryParam('number');
        $option = Craft::$app->getRequest()->getQueryParam('option', '');

        if (!$number) {
            throw new HttpInvalidParamException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException('404','Order not found');
        }

        $pdf = Plugin::getInstance()->getPdf()->renderPdfForOrder($order, $option);
        $filenameFormat = Plugin::getInstance()->getSettings()->orderPdfFilenameFormat;

        $fileName = $this->getView()->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName) {
            $fileName = 'Order-' . $order->number;
        }

        return Craft::$app->getResponse()->sendContentAsFile($pdf, $fileName . '.pdf', [
            'mimeType' => 'application/pdf'
        ]);
    }

    /**
     * Returns the export file in the requested format.
     *
     * @throws HttpException
     */
    public function actionExportOrder(): Response
    {
        $this->requirePermission('commerce-manageOrders');

        $format = Craft::$app->getRequest()->getRequiredParam('format');
        $startDate = Craft::$app->getRequest()->getRequiredParam('startDate');
        $endDate = Craft::$app->getRequest()->getRequiredParam('endDate');
        $source = Craft::$app->getRequest()->getRequiredParam('source');

        // Limited to only the formats we allow.
        $allowedFormats = ['xls', 'csv', 'xlsx', 'ods',];
        if (!in_array($format, $allowedFormats, false)) {
            throw new BadRequestHttpException();
        }

        if (strpos($source, ':') !== false) {
            $sourceHandle = explode(':', $source)[1];
        }

        // null order status is ok, will then find all order statuses
        $orderStatusId = isset($sourceHandle) ? Plugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($sourceHandle)->id : null;

        // Get the generated file saved into a temporary location
        $tempFile = Plugin::getInstance()->getReports()->getOrdersExportFile($format, $startDate, $endDate, $orderStatusId);

        return Craft::$app->getResponse()->sendFile($tempFile, 'orders.' . $format);
    }
}
