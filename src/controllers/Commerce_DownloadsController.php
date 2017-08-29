<?php
namespace Craft;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Class Commerce_DownloadsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_DownloadsController extends Commerce_BaseFrontEndController
{
    public function actionPdf()
    {
        $template = craft()->commerce_settings->getSettings()->orderPdfPath;
        $filenameFormat = craft()->commerce_settings->getSettings()->orderPdfFilenameFormat;

        // Set Craft to the site template mode
        $templatesService = craft()->templates;
        $oldTemplateMode = $templatesService->getTemplateMode();
        $templatesService->setTemplateMode(TemplateMode::Site);

        if(!$template || !$templatesService->doesTemplateExist($template))
        {
            // Restore the original template mode
            $templatesService->setTemplateMode($oldTemplateMode);

            throw new HttpException(404, 'Template does not exist.');
        };

        $number = craft()->request->getQuery('number');
        $option = craft()->request->getQuery('option', '');
        $order = craft()->commerce_orders->getOrderByNumber($number);
        if (!$order) {
            throw new HttpException(404);
        }

        $fileName = craft()->templates->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName)
        {
            $fileName = "Order-".$order->number;
        }

        $html = $templatesService->render($template, compact('order', 'option'));

        // Set the config options
        $pathService = craft()->path;
        $dompdfTempDir = $pathService->getTempPath().'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath().'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath().'commerce_dompdf.htm';
        IOHelper::ensureFolderExists($dompdfTempDir);
        IOHelper::ensureFolderExists($dompdfFontCache);

        $isRemoteEnabled = craft()->config->get('pdfAllowRemoteImages', 'commerce');

        $options = new Options([
            'tempDir' => $dompdfTempDir,
            'fontCache' => $dompdfFontCache,
            'logOutputFile' => $dompdfLogFile,
            'isRemoteEnabled' => $isRemoteEnabled
        ]);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        // Set the paper size/orientation
        $size = craft()->config->get('pdfPaperSize', 'commerce');
        $orientation = craft()->config->get('pdfPaperOrientation', 'commerce');
        $dompdf->set_paper($size, $orientation);

        $dompdf->render();
        $dompdf->stream($fileName . ".pdf");

        // Restore the original template mode
        $templatesService->setTemplateMode($oldTemplateMode);

        craft()->end();
    }
}
