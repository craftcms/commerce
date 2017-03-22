<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\helpers\FileHelper;
use Dompdf\Dompdf;
use Dompdf\Options;
use craft\web\View;
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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Downloads extends BaseFrontEnd
{
    public function actionPdf()
    {
        $template = Plugin::getInstance()->getSettings()->getSettings()->orderPdfPath;
        $filenameFormat = Plugin::getInstance()->getSettings()->getSettings()->orderPdfFilenameFormat;

        // Set Craft to the site template mode
        $viewService = Craft::$app->getView();
        $oldTemplateMode = $viewService->getTemplateMode();
        $viewService->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$template || !$viewService->doesTemplateExist($template)) {
            // Restore the original template mode
            $viewService->setTemplateMode($oldTemplateMode);

            throw new HttpException(404, 'Template does not exist.');
        };

        $number = Craft::$app->getRequest()->getQuery('number');
        $option = Craft::$app->getRequest()->getQuery('option', '');
        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);
        if (!$order) {
            throw new HttpException(404);
        }

        $fileName = Craft::$app->getView()->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName) {
            $fileName = "Order-".$order->number;
        }

        $html = $viewService->render($template, compact('order', 'option'));

        $dompdf = new Dompdf();

        // Set the config options
        $pathService = Craft::$app->getPath();
        $dompdfTempDir = $pathService->getTempPath().'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath().'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath().'commerce_dompdf.htm';
        FileHelper::isWritable($dompdfTempDir);
        FileHelper::isWritable($dompdfFontCache);

        $options = new Options();
        $options->setTempDir($dompdfTempDir);
        $options->setFontCache($dompdfFontCache);
        $options->setLogOutputFile($dompdfLogFile);
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream($fileName.".pdf");

        // Restore the original template mode
        $viewService->setTemplateMode($oldTemplateMode);

        Craft::$app->end();
    }
}
