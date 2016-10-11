<?php
namespace Craft;

if (!defined('DOMPDF_ENABLE_AUTOLOAD')) {
    // disable DOMPDF's internal autoloader since we are using Composer
    define('DOMPDF_ENABLE_AUTOLOAD', false);
    // include DOMPDF's configuration
    require_once __DIR__ . '/../vendor/dompdf/dompdf/dompdf_config.inc.php';
}

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

        $dompdf = new \DOMPDF();

        // Set the config options
        $pathService = craft()->path;
        $dompdfTempDir = $pathService->getTempPath().'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath().'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath().'commerce_dompdf.htm';
        IOHelper::ensureFolderExists($dompdfTempDir);
        IOHelper::ensureFolderExists($dompdfFontCache);
        $dompdf->set_option('temp_dir', $dompdfTempDir);
        $dompdf->set_option('font_cache', $dompdfFontCache);
        $dompdf->set_option('log_output_file', $dompdfLogFile);

        $dompdf->load_html($html);
        $dompdf->render();
        $dompdf->stream($fileName . ".pdf");

        // Restore the original template mode
        $templatesService->setTemplateMode($oldTemplateMode);

        craft()->end();
    }
}
