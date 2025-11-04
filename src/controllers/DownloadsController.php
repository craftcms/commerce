<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Locale;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use craft\web\View;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\web\BadRequestHttpException;
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
     * Renders the email challenge template with the provided parameters.
     *
     * @param Order $order The order to display
     * @param string $orderNumber The order number
     * @param string|null $pdfHandle The PDF handle
     * @param string $option The PDF option
     * @param bool $inline Whether to display inline
     * @param array $errors Optional errors to display
     * @param string|null $email Optional email value to pre-fill
     * @return Response
     * @since 4.9.5
     */
    private function renderEmailChallenge(
        Order $order,
        string $orderNumber,
        ?string $pdfHandle,
        string $option,
        bool $inline,
        array $errors = [],
        ?string $email = null,
    ): Response {
        $params = [
            'order' => $order,
            'orderNumber' => $orderNumber,
            'pdfHandle' => $pdfHandle,
            'option' => $option,
            'inline' => $inline,
        ];

        if (!empty($errors)) {
            $params['errors'] = $errors;
        }

        if ($email !== null) {
            $params['email'] = $email;
        }

        return $this->renderTemplate('commerce/_downloads/email-challenge', $params, View::TEMPLATE_MODE_CP);
    }

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
            throw new BadRequestHttpException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        // Don't allow PDF downloads for carts without an email
        if (!$order->getEmail()) {
            throw new HttpException(404, 'Order not found');
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        $hasValidToken = false;

        // Check if token is provided and valid (works for anyone, logged in or not)
        if ($token) {
            $tokenData = Craft::$app->getTokens()->getTokenRoute($token);

            // Validate token structure and order number
            if (!$tokenData || !isset($tokenData[1]['orderNumber']) || $tokenData[1]['orderNumber'] !== $number) {
                // Invalid token - redirect to challenge form with error
                Craft::$app->getSession()->setError(Craft::t('commerce', 'The download link is invalid. Please request a new one.'));
                return $this->redirect(UrlHelper::actionUrl('commerce/downloads/email-challenge', [
                    'number' => $number,
                    'pdfHandle' => $pdfHandle,
                    'option' => $option,
                    'inline' => $inline,
                ]));
            }

            // Check if token has expired based on the timestamp in the token data
            if (isset($tokenData[1]['expiresAt'])) {
                $expiresAt = $tokenData[1]['expiresAt'];
                $now = (new \DateTime())->getTimestamp();

                if ($now > $expiresAt) {
                    // Token expired - redirect to email challenge form
                    return $this->redirect(UrlHelper::actionUrl('commerce/downloads/email-challenge', [
                        'number' => $number,
                        'pdfHandle' => $pdfHandle,
                        'option' => $option,
                        'inline' => $inline,
                    ]));
                }
            }

            // Token is valid
            $hasValidToken = true;
        }

        // Check user permissions if no valid token
        if (!$hasValidToken) {
            if ($currentUser) {
                // Check if user is the order customer, admin, or has permission to manage orders
                $isOrderCustomer = $order->getCustomer() && $order->getCustomer()->id === $currentUser->id;
                $hasPermission = $currentUser->admin || $order->canView($currentUser);

                if (!($isOrderCustomer || $hasPermission)) {
                    throw new HttpException(403, 'You do not have permission to view this order');
                }
            } else {
                // Anonymous user without valid token - redirect to email challenge form
                return $this->redirect(UrlHelper::actionUrl('commerce/downloads/email-challenge', [
                    'number' => $number,
                    'pdfHandle' => $pdfHandle,
                    'option' => $option,
                    'inline' => $inline,
                ]));
            }
        }

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
        $originalFormattingLocale = Craft::$app->formattingLocale;

        $language = $pdf->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $renderedPdf = Plugin::getInstance()->getPdfs()->renderPdfForOrder($order, $option, null, [], $pdf);

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale);

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
     * Displays the email challenge form for anonymous users trying to download an order PDF
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEmailChallenge(): Response
    {
        $number = $this->request->getQueryParam('number');
        $pdfHandle = $this->request->getQueryParam('pdfHandle');
        $option = $this->request->getQueryParam('option', '');
        $inline = (bool) $this->request->getQueryParam('inline', false);

        if (!$number) {
            throw new BadRequestHttpException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        // Don't allow PDF downloads for carts without an email
        if (!$order->getEmail()) {
            throw new HttpException(404, 'Order not found');
        }

        return $this->renderEmailChallenge($order, $number, $pdfHandle, $option, $inline);
    }

    /**
     * Handles the email challenge form submission for anonymous users trying to download an order PDF
     *
     * @throws HttpException
     * @throws Exception
     */
    public function actionPdfChallenge(): Response
    {
        $this->requirePostRequest();

        $orderNumberHash = $this->request->getBodyParam('orderNumberHash');
        $pdfHandle = $this->request->getBodyParam('pdfHandle');
        $option = $this->request->getBodyParam('option', '');
        $inline = (bool) $this->request->getBodyParam('inline', false);

        if (!$orderNumberHash) {
            throw new BadRequestHttpException('Order number hash is required');
        }

        // Validate the order number hash
        $orderNumber = Craft::$app->getSecurity()->validateData($orderNumberHash);

        if ($orderNumber === false) {
            throw new BadRequestHttpException('Invalid order number hash');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($orderNumber);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        // Build the download URL with the token using the Pdfs service
        $downloadUrl = Plugin::getInstance()->getPdfs()->getPdfUrl($order, $option, $pdfHandle, $inline);

        // Send email using system message
        $systemMessage = Craft::$app->getSystemMessages()->getMessage('commerce_pdf_download', $order->getOrderSite()->language);

        if (!Craft::$app->getMailer()->composeFromKey('commerce_pdf_download', [
            'link' => $downloadUrl,
            'order' => $order,
        ])->setTo($order->email)->send()) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Failed to send email. Please try again.'));
            return $this->renderEmailChallenge($order, $orderNumber, $pdfHandle, $option, $inline);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'A new download link has been sent to {email}', ['email' => $order->getMaskedEmail()]));

        // Redirect to success page to prevent duplicate submissions on refresh
        return $this->redirect(UrlHelper::actionUrl('commerce/downloads/pdf-sent', ['hash' => $orderNumberHash]));
    }

    /**
     * Displays the success page after email challenge is completed
     *
     * @return Response
     * @throws HttpException
     */
    public function actionPdfSent(): Response
    {
        $orderNumberHash = $this->request->getQueryParam('hash');

        if (!$orderNumberHash) {
            throw new BadRequestHttpException('Hash parameter required');
        }

        // Validate and extract the order number from the hash
        $orderNumber = Craft::$app->getSecurity()->validateData($orderNumberHash);

        if ($orderNumber === false) {
            throw new HttpException(400, 'Invalid hash parameter');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($orderNumber);

        if (!$order) {
            throw new HttpException(404, 'Order not found');
        }

        return $this->renderTemplate('commerce/_downloads/email-sent', [
            'email' => $order->getMaskedEmail(),
        ], View::TEMPLATE_MODE_CP);
    }
}
