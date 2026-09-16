<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Contracts;

interface RequestResponseInterface
{
    public function isSuccessful(): bool;

    public function isProcessing(): bool;

    public function isRedirect(): bool;

    public function getRedirectMethod(): string;

    public function getRedirectData(): array;

    public function getRedirectUrl(): string;

    public function getTransactionReference(): string;

    public function getCode(): string;

    public function getData(): mixed;

    public function getMessage(): string;

    public function redirect(): void;
}
