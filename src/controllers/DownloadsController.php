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
use craft\commerce\records\Email;
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
     * @return Response
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

        if (!$number) {
            throw new HttpInvalidParamException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException('404', 'Order not found');
        }

        $pdf = null;

        if ($pdfHandle) {
            $pdf = Plugin::getInstance()->getPdfs()->getPdfByHandle($pdfHandle);

            if (!$pdf) {
                throw new InvalidCallException("Can not find the PDF to render based on the handle supplied.");
            }
        } else {
            $pdf = Plugin::getInstance()->getPdfs()->getDefaultPdf();
        }

        if (!$pdf) {
            throw new InvalidCallException("Can not find a PDF to render.");
        }

        $originalLanguage = Craft::$app->language;

        $language = $pdf->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $renderedPdf = Plugin::getInstance()->getPdfs()->renderPdfForOrder($order, $option, null, [], $pdf);

        // Set previous language back
        Craft::$app->language = $originalLanguage;
        Craft::$app->set('locale', Craft::$app->getI18n()->getLocaleById($originalLanguage));

        $fileName = $this->getView()->renderObjectTemplate((string)$pdf->fileNameFormat, $order);
        if (!$fileName) {
            $fileName = $pdf->handle . '-' . $order->number;
        }

        return Craft::$app->getResponse()->sendContentAsFile($renderedPdf, $fileName . '.pdf', [
            'mimeType' => 'application/pdf'
        ]);
    }
}
