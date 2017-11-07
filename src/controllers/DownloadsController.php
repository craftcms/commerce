<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\helpers\FileHelper;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
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
        $template = Plugin::getInstance()->getSettings()->orderPdfPath;
        $filenameFormat = Plugin::getInstance()->getSettings()->orderPdfFilenameFormat;

        // Set Craft to the site template mode
        $view = $this->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$template || !$view->doesTemplateExist($template)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            throw new HttpException(404, 'Template does not exist.');
        }

        $number = Craft::$app->getRequest()->getQuery('number');
        $option = Craft::$app->getRequest()->getQuery('option', '');
        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);
        if (!$order) {
            throw new HttpException(404);
        }

        $fileName = $this->getView()->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName) {
            $fileName = 'Order-'.$order->number;
        }

        $html = $view->render($template, compact('order', 'option'));

        $dompdf = new Dompdf();

        // Set the config options
        $pathService = Craft::$app->getPath();
        $dompdfTempDir = $pathService->getTempPath().'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath().'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath().'commerce_dompdf.htm';
        FileHelper::isWritable($dompdfTempDir);
        FileHelper::isWritable($dompdfFontCache);

        $isRemoteEnabled = Plugin::getInstance()->getSettings()->pdfAllowRemoteImages;

        $options = new Options();
        $options->setTempDir($dompdfTempDir);
        $options->setFontCache($dompdfFontCache);
        $options->setLogOutputFile($dompdfLogFile);
        $options->setIsRemoteEnabled($isRemoteEnabled);

        // Paper Size and Orientation
        $pdfPaperSize = Plugin::getInstance()->getSettings()->pdfPaperSize;
        $pdfPaperOrientation = Plugin::getInstance()->getSettings()->pdfPaperOrientation;
        $options->setDefaultPaperOrientation($pdfPaperOrientation);
        $options->setDefaultPaperSize($pdfPaperSize);

        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream($fileName.'.pdf');

        // Restore the original template mode
        $view->setTemplateMode($oldTemplateMode);

        Craft::$app->end();
    }
}
