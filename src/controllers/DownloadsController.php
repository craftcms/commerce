<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\web\HttpException;

if (!defined('DOMPDF_ENABLE_AUTOLOAD')) {
    // disable DOMPDF's internal autoloader since we are using Composer
    define('DOMPDF_ENABLE_AUTOLOAD', false);
    // include DOMPDF's configuration
    require_once __DIR__.'/../vendor/dompdf/dompdf/dompdf_config.inc.php';
}

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
    public function actionPdf()
    {

        $number = Craft::$app->getRequest()->getQuery('number');
        $option = Craft::$app->getRequest()->getQuery('option', '');
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

        Craft::$app->end();
    }
}
