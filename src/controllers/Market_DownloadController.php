<?php

namespace Craft;

class Market_DownloadController extends Market_BaseController
{
    protected $allowAnonymous = ['actionPdf'];

    public function actionPdf()
    {
        $template = craft()->config->get('orderPdfPath','market');
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