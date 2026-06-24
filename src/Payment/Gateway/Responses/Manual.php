<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Responses;

use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

class Manual implements RequestResponseInterface
{
    #[\Override]
    public function isSuccessful(): bool
    {
        return true;
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
        return '';
    }

    #[\Override]
    public function getMessage(): string
    {
        return '';
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
