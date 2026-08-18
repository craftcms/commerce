<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\elements\Order;
use craft\commerce\helpers\Locale;
use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Pdf\Pdfs;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

readonly class DownloadsController
{
    public function pdf(Request $request): Response
    {
        $number = $request->query('number');
        $pdfHandle = $request->query('pdfHandle');
        $option = $request->query('option', '');
        $inline = (bool)$request->query('inline', false);
        $token = $request->query('code') ?? $request->query('token');

        abort_unless($number, 400, 'Order number required');

        $order = app(Orders::class)->getOrderByNumber($number);
        abort_if(!$order || !$order->getEmail(), 404, 'Order not found');

        $currentUser = currentUserElement();
        $hasValidToken = false;

        if ($token) {
            $tokenData = app(RouteTokens::class)->getTokenRoute($token);

            if (!$tokenData || !isset($tokenData[1]['orderNumber']) || $tokenData[1]['orderNumber'] !== $number) {
                session()->flash('error', t('The download link has expired. Please request a new one.', category: 'commerce'));
                return redirect(Url::actionUrl('commerce/downloads/email-challenge', [
                    'number' => $number,
                    'pdfHandle' => $pdfHandle,
                    'option' => $option,
                    'inline' => $inline,
                ]));
            }

            $hasValidToken = true;
        }

        if (!$hasValidToken) {
            $challengeUrl = Url::actionUrl('commerce/downloads/email-challenge', [
                'number' => $number,
                'pdfHandle' => $pdfHandle,
                'option' => $option,
                'inline' => $inline,
            ]);

            if ($currentUser) {
                $isOrderCustomer = $order->getCustomer() && $order->getCustomer()->id === $currentUser->id;
                $hasPermission = $currentUser->admin || $order->canView($currentUser);

                if (!($isOrderCustomer || $hasPermission)) {
                    return redirect($challengeUrl);
                }
            } else {
                return redirect($challengeUrl);
            }
        }

        if ($pdfHandle) {
            $pdf = app(Pdfs::class)->getPdfByHandle($pdfHandle, $order->storeId);
            abort_if(!$pdf, 500, 'Can not find the PDF to render based on the handle supplied.');
        } else {
            $pdf = app(Pdfs::class)->getDefaultPdf($order->storeId);
        }

        abort_if(!$pdf, 500, 'Can not find a PDF to render.');

        $originalLanguage = \Craft::$app->language;
        $originalFormattingLocale = \Craft::$app->formattingLocale;

        $language = $pdf->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $renderedPdf = app(Pdfs::class)->renderPdfForOrder($order, $option, null, [], $pdf);

        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);

        $fileName = renderSandboxedObjectTemplate((string)$pdf->fileNameFormat, $order) ?: ($pdf->handle . '-' . $order->number);

        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $fileName . '.pdf') . '"';

        return response($renderedPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }

    public function emailChallenge(Request $request): string
    {
        $number = $request->query('number');
        abort_unless($number, 400, 'Order number required');

        $order = app(Orders::class)->getOrderByNumber($number);
        abort_if(!$order || !$order->getEmail(), 404, 'Order not found');

        return $this->renderEmailChallenge(
            $order,
            $number,
            $request->query('pdfHandle'),
            $request->query('option', ''),
            (bool)$request->query('inline', false),
        );
    }

    public function pdfChallenge(Request $request): string|Response
    {
        $orderNumberHash = $request->input('orderNumberHash');
        $pdfHandle = $request->input('pdfHandle');
        $option = $request->input('option', '');
        $inline = (bool)$request->input('inline', false);

        abort_unless($orderNumberHash, 400, 'Order number hash is required');

        try {
            $orderNumber = Crypt::decrypt($orderNumberHash);
        } catch (DecryptException) {
            $orderNumber = false;
        }
        abort_if($orderNumber === false, 400, 'Invalid order number hash');

        $order = app(Orders::class)->getOrderByNumber($orderNumber);
        abort_if(!$order, 404, 'Order not found');

        $downloadUrl = app(Pdfs::class)->getPdfUrl($order, $option, $pdfHandle, $inline);

        try {
            $sent = Mail::to($order->email)->send(new SystemMessageMailable(
                key: 'commerce_pdf_download',
                variables: [
                    'link' => $downloadUrl,
                    'order' => $order,
                ],
            ));
        } catch (Throwable) {
            $sent = null;
        }

        if ($sent === null) {
            session()->flash('error', t('Failed to send email. Please try again.', category: 'commerce'));
            return $this->renderEmailChallenge($order, $orderNumber, $pdfHandle, $option, $inline);
        }

        session()->flash('notice', t('A new download link has been sent to {email}', ['email' => $order->getMaskedEmail()], category: 'commerce'));

        return redirect(Url::actionUrl('commerce/downloads/pdf-sent', ['hash' => $orderNumberHash]));
    }

    public function pdfSent(Request $request): string
    {
        $orderNumberHash = $request->query('hash');
        abort_unless($orderNumberHash, 400, 'Hash parameter required');

        try {
            $orderNumber = Crypt::decrypt($orderNumberHash);
        } catch (DecryptException) {
            $orderNumber = false;
        }
        abort_if($orderNumber === false, 400, 'Invalid hash parameter');

        $order = app(Orders::class)->getOrderByNumber($orderNumber);
        abort_if(!$order, 404, 'Order not found');

        return pageTemplate('commerce/_downloads/email-sent', [
            'email' => $order->getMaskedEmail(),
        ], TemplateMode::Cp);
    }

    private function renderEmailChallenge(
        Order $order,
        string $orderNumber,
        ?string $pdfHandle,
        string $option,
        bool $inline,
    ): string {
        return pageTemplate('commerce/_downloads/email-challenge', [
            'order' => $order,
            'orderNumber' => $orderNumber,
            'pdfHandle' => $pdfHandle,
            'option' => $option,
            'inline' => $inline,
        ], TemplateMode::Cp);
    }
}
