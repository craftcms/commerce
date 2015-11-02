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
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_DownloadsController extends Commerce_BaseFrontEndController
{
    public function actionPdf()
    {
        $template = craft()->commerce_settings->getSettings()->orderPdfPath;
        $number = craft()->request->getQuery('number');
        $option = craft()->request->getQuery('option', '');
        $order = craft()->commerce_orders->getOrderByNumber($number);
        if (!$order) {
            throw new HttpException(404);
        }
        $originalPath = craft()->path->getTemplatesPath();
        $newPath = craft()->path->getSiteTemplatesPath();
        craft()->path->setTemplatesPath($newPath);
        $html = craft()->templates->render($template, compact('order', 'option'));

        $dompdf = new \DOMPDF();
        $dompdf->load_html($html);
        $dompdf->render();
        $dompdf->stream("Order-" . $number . ".pdf");

        craft()->path->setTemplatesPath($originalPath);
        craft()->end();
    }
}
