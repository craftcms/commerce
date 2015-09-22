<?php
namespace Craft;

/**
 * Class Commerce_DownloadController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_DownloadController extends Commerce_BaseController
{
	protected $allowAnonymous = ['actionPdf'];

	public function actionPdf ()
	{
		$template = craft()->commerce_settings->getSettings()->orderPdfPath;
		$number = craft()->request->getQuery('number');
		$option = craft()->request->getQuery('option', '');
		$order = craft()->commerce_order->getByNumber($number);
		if (!$order)
		{
			throw new HttpException(404);
		}
		$originalPath = craft()->path->getTemplatesPath();
		$newPath = craft()->path->getSiteTemplatesPath();
		craft()->path->setTemplatesPath($newPath);
		$html = craft()->templates->render($template, compact('order', 'option'));

		$dompdf = new \DOMPDF();
		$dompdf->load_html($html);
		$dompdf->render();
		$dompdf->stream("Order-".$number.".pdf");

		craft()->path->setTemplatesPath($originalPath);
	}
}