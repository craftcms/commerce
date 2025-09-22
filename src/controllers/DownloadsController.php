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
use craft\helpers\UrlHelper;
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
        $token = $this->request->getQueryParam('token');

        if (!$number) {
            throw new HttpInvalidParamException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        // Check if token is provided and valid
        if ($token) {
            $tokenData = Craft::$app->getTokens()->getTokenRoute($token);
            if (!$tokenData || (!isset($tokenData[1]['orderNumber']) || $tokenData[1]['orderNumber'] !== $number)) {
                return $this->renderTemplate('commerce/_downloads/email-challenge', [
                    'errors' => ['token' => ['The download link is invalid or has expired. Please request a new one.']],
                    'order' => $order,
                    'orderNumber' => $number,
                    'pdfHandle' => $pdfHandle,
                    'option' => $option,
                    'inline' => $inline,
                ]);
            }
        } else {
            // No token provided, check user permissions
            if ($currentUser) {
                // Check if user is admin or can view the order
                if (!$currentUser->admin && !$order->canView($currentUser)) {
                    throw new HttpException(403, 'You do not have permission to view this order');
                }
            } else {
                // Anonymous user without token - show email challenge form
                return $this->renderTemplate('commerce/_downloads/email-challenge', [
                    'order' => $order,
                    'orderNumber' => $number,
                    'pdfHandle' => $pdfHandle,
                    'option' => $option,
                    'inline' => $inline,
                ]);
            }
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

    /**
     * Handles the email challenge for anonymous users trying to download an order PDF
     *
     * @throws HttpException
     * @throws Exception
     */
    public function actionPdfChallenge(): Response
    {
        $this->requirePostRequest();

        $orderNumber = $this->request->getBodyParam('orderNumber');
        $email = $this->request->getBodyParam('email');
        $pdfHandle = $this->request->getBodyParam('pdfHandle');
        $option = $this->request->getBodyParam('option', '');
        $inline = (bool) $this->request->getBodyParam('inline', false);

        if (!$orderNumber || !$email) {
            throw new HttpInvalidParamException('Order number and email are required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($orderNumber);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        // Check if the provided email matches the order's email
        if (strcasecmp($order->email, $email) !== 0) {
            return $this->renderTemplate('commerce/_downloads/email-challenge', [
                'errors' => ['email' => ['The provided email does not match our records for this order.']],
                'order' => $order,
                'orderNumber' => $orderNumber,
                'pdfHandle' => $pdfHandle,
                'option' => $option,
                'inline' => $inline,
                'email' => $email,
            ]);
        }

        // Create a one-time token for PDF download
        $token = Craft::$app->getTokens()->createToken([
            'commerce/downloads/pdf',
            ['orderNumber' => $orderNumber]
        ]);

        // Build the download URL with the token
        $downloadUrl = UrlHelper::siteUrl('actions/commerce/downloads/pdf', [
            'number' => $orderNumber,
            'token' => $token,
            'pdfHandle' => $pdfHandle,
            'option' => $option,
            'inline' => $inline,
        ]);

        // Send email using system message
        $systemMessage = Craft::$app->getSystemMessages()->getMessage('commerce_pdf_download', $order->siteId);

        if (!Craft::$app->getMailer()->composeFromKey('commerce_pdf_download', [
            'link' => $downloadUrl,
        ])->setTo($order->email)->send()) {
            Craft::$app->getSession()->setError('Failed to send email. Please try again.');
            return $this->renderTemplate('commerce/_downloads/email-challenge', [
                'order' => $order,
                'orderNumber' => $orderNumber,
                'pdfHandle' => $pdfHandle,
                'option' => $option,
                'inline' => $inline,
                'email' => $email,
            ]);
        }

        Craft::$app->getSession()->setNotice('A download link has been sent to ' . $email);

        // Render a success page
        return $this->renderTemplate('commerce/_downloads/email-sent', [
            'email' => $email,
        ]);
    }
}
