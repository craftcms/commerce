<?php
namespace Craft;

/**
 * Class Market_DownloadController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_DownloadController extends Market_BaseController
{
    protected $allowAnonymous = ['actionPdf'];

    public function actionPdf()
    {
        $template = craft()->market_settings->getSettings()->orderPdfPath;
        $number = craft()->request->getQuery('number');
        $option = craft()->request->getQuery('option','');
        $order = craft()->market_order->getByNumber($number);
        if(!$order){
            throw new HttpException(404);
        }
        $originalPath = craft()->path->getTemplatesPath();
        $newPath = craft()->path->getSiteTemplatesPath();
        craft()->path->setTemplatesPath($newPath);
        $html = craft()->templates->render($template,compact('order','option'));

        $dompdf = new \DOMPDF();
        $dompdf->load_html($html);
        $dompdf->render();
        $dompdf->stream("Order-".$number.".pdf");

        craft()->path->setTemplatesPath($originalPath);
    }
}