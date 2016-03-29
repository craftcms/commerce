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

        $originalPath = craft()->path->getTemplatesPath();
        $newPath = craft()->path->getSiteTemplatesPath();
        craft()->path->setTemplatesPath($newPath);

        if(!$template || !craft()->templates->doesTemplateExist($template))
        {
            craft()->path->setTemplatesPath($originalPath);
            throw new HttpException(404, 'Template does not exist.');
        };

        $number = craft()->request->getQuery('number');
        $option = craft()->request->getQuery('option', '');
        $order = craft()->commerce_orders->getOrderByNumber($number);
        if (!$order) {
            throw new HttpException(404);
        }

        $html = craft()->templates->render($template, compact('order', 'option'));

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
        $dompdf->stream("Order-" . $number . ".pdf");

        craft()->path->setTemplatesPath($originalPath);
        craft()->end();
    }
}
