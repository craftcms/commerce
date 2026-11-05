<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\SystemMessage\Models\SystemMessage;
use Override;

use function CraftCms\Cms\t;

trait HasSystemMessages
{
    #[Override]
    protected function getSystemMessages(): array
    {
        return [
            'commerce_pdf_download' => fn() => new SystemMessage([
                'key' => 'commerce_pdf_download',
                'heading' => t('Order PDF Download Link', category: 'commerce'),
                'subject' => t('Your Order PDF Download Link', category: 'commerce'),
                'body' => $this->defaultPdfDownloadMessage(),
            ]),
            'commerce_cart_recovery' => fn() => new SystemMessage([
                'key' => 'commerce_cart_recovery',
                'heading' => t('Cart Recovery Link', category: 'commerce'),
                'subject' => t('Your Cart Recovery Link', category: 'commerce'),
                'body' => $this->defaultCartRecoveryMessage(),
            ]),
            'commerce_payment_link' => fn() => new SystemMessage([
                'key' => 'commerce_payment_link',
                'heading' => t('Payment Link', category: 'commerce'),
                'subject' => t('Your Payment Link', category: 'commerce'),
                'body' => $this->defaultPaymentLinkMessage(),
                'variables' => [
                    'link' => t('The hosted payment page', category: 'commerce'),
                    'amount' => t('The amount to pay', category: 'commerce'),
                    'expiresAt' => t('When the link stops working', category: 'commerce'),
                    'order' => t('The order', category: 'commerce'),
                ],
            ]),
        ];
    }

    /**
     * Returns the default message body for the payment link email.
     */
    private function defaultPaymentLinkMessage(): string
    {
        return "Hello,\n\n" .
            "You can pay {{ amount }} for your order using the link below:\n\n" .
            "[Pay now]({{ link }})\n\n" .
            "**Please note:** This link will expire for security purposes.\n\n" .
            "Thank you!";
    }

    /**
     * Returns the default message body for the PDF download email.
     */
    private function defaultPdfDownloadMessage(): string
    {
        return "Hello,\n\n" .
            "You requested a PDF download for your order. Click the link below to download your PDF:\n\n" .
            "[Download PDF]({{ link }})\n\n" .
            "**Please note:** This link will expire for security purposes.\n\n" .
            "Thank you!";
    }

    /**
     * Returns the default message body for the cart recovery email.
     */
    private function defaultCartRecoveryMessage(): string
    {
        return "Hello,\n\n" .
            "You requested a link to recover your shopping cart. Click the link below to continue shopping:\n\n" .
            "[Recover My Cart]({{ link }})\n\n" .
            "**Please note:** This link will expire for security purposes.\n\n" .
            "Thank you!";
    }
}
