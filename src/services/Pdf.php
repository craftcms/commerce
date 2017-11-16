<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use yii\base\Component;
use craft\commerce\Plugin;
use craft\helpers\FileHelper;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use yii\base\Exception;
use yii\web\HttpException;
/**
 * Pdf service.
 *
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Pdf extends Component
{
    /**
     * Returns a rendered PDF object for the order.
     *
     * @param Order  $order
     * @param string $option
     *
     * @return Dompdf
     * @throws Exception
     */
    public function pdfForOrder(Order $order, $option = '')
    {
        $template = Plugin::getInstance()->getSettings()->orderPdfPath;

        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$template || !$view->doesTemplateExist($template)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            throw new Exception('Template file does not exist.');
        }

        if (!$order) {
            throw new Exception('No Order Found');
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

        // Restore the original template mode
        $view->setTemplateMode($oldTemplateMode);

        return $dompdf;
    }
}
