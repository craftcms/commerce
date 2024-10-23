<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\Locale;
use craft\commerce\Plugin;
use HttpInvalidParamException;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\web\HttpException;
use yii\web\RangeNotSatisfiableHttpException;
use yii\web\Response;

/**
 * Class Downloads Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DownloadsController extends BaseFrontEndController
{
    /**
     * @throws HttpException
     * @throws Throwable
     * @throws Exception
     * @throws RangeNotSatisfiableHttpException
     */
    public function actionPdf(): Response
    {
        $number = $this->request->getQueryParam('number');
        $pdfHandle = $this->request->getQueryParam('pdfHandle');
        $option = $this->request->getQueryParam('option', '');
        $inline = (bool) $this->request->getQueryParam('inline', false);

        if (!$number) {
            throw new HttpInvalidParamException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        if ($pdfHandle) {
            $pdf = Plugin::getInstance()->getPdfs()->getPdfByHandle($pdfHandle, $order->storeId);

            if (!$pdf) {
                throw new InvalidCallException("Can not find the PDF to render based on the handle supplied.");
            }
        } else {
            $pdf = Plugin::getInstance()->getPdfs()->getDefaultPdf($order->storeId);
        }

        if (!$pdf) {
            throw new InvalidCallException("Can not find a PDF to render.");
        }

        $originalLanguage = Craft::$app->language;
        $originalFormattingLocale = Craft::$app->formattingLocale;

        $language = $pdf->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $renderedPdf = Plugin::getInstance()->getPdfs()->renderPdfForOrder($order, $option, null, [], $pdf);

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);

        $fileName = $this->getView()->renderObjectTemplate((string)$pdf->fileNameFormat, $order);
        if (!$fileName) {
            $fileName = $pdf->handle . '-' . $order->number;
        }

        return $this->response->sendContentAsFile($renderedPdf, $fileName . '.pdf', [
            'mimeType' => 'application/pdf',
            'inline' => $inline,
        ]);
    }
}
