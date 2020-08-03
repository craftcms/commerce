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
            throw new HttpException('404', 'Order not found');
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
}
