<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\web\HttpException;

/**
 * Class Downloads Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class DownloadsController extends BaseFrontEndController
{
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function actionPdf(): Response
    {

        $number = Craft::$app->getRequest()->getQueryParam('number');
        $option = Craft::$app->getRequest()->getQueryParam('option', '');
        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException('No Order Found');
        }

        $pdf = Plugin::getInstance()->getPdf()->pdfForOrder($order, $option);
        $filenameFormat = Plugin::getInstance()->getSettings()->orderPdfFilenameFormat;

        $fileName = $this->getView()->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName) {
            $fileName = 'Order-'.$order->number;
        }

        $pdf->stream($fileName.'.pdf');
    }
}
