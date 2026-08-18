<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Responses;

use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

class Dummy implements RequestResponseInterface
{
    private bool $_success = true;

    public function __construct(?CreditCardPaymentForm $form = null)
    {
        if ($form === null) {
            $this->_success = false;
            return;
        }

        // Token populated? This is a "payment source" so no need to fail anything.
        if ($form->token) {
            return;
        }

        $number = (string)$form->number;
        $isValid = ((int)substr($number, -1) % 2 === 0);

        if (!$isValid) {
            $this->_success = false;
        }
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->_success;
    }

    #[\Override]
    public function isRedirect(): bool
    {
        return false;
    }

    #[\Override]
    public function getRedirectMethod(): string
    {
        return '';
    }

    #[\Override]
    public function getRedirectData(): array
    {
        return [];
    }

    #[\Override]
    public function getRedirectUrl(): string
    {
        return '';
    }

    #[\Override]
    public function getTransactionReference(): string
    {
        return date('Y-m-d-H-i-s');
    }

    #[\Override]
    public function getCode(): string
    {
        return $this->_success ? '' : 'payment.failed';
    }

    #[\Override]
    public function getMessage(): string
    {
        return $this->_success ? '' : t('Dummy gateway payment failed.', category: 'commerce');
    }

    #[\Override]
    public function redirect(): void
    {
    }

    #[\Override]
    public function getData(): mixed
    {
        return '';
    }

    #[\Override]
    public function isProcessing(): bool
    {
        return false;
    }
}
