<?php

namespace craft\commerce\base;

/**
 * This is a dummy gateway request response.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     2.0
 */
class DummyRequestResponse implements RequestResponseInterface
{
    /**
     * @inheritdocs
     */
    public function isSuccessful(): bool
    {
        return true;
    }

    /**
     * @inheritdocs
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @inheritdocs
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritdocs
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdocs
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @inheritdocs
     */
    public function getTransactionReference(): string
    {
        return date('Y-m-d-H-i-s');
    }

    /**
     * @inheritdocs
     */
    public function getCode(): string
    {
        return '';
    }

    /**
     * @inheritdocs
     */
    public function getMessage(): string
    {
        return '';
    }

    /**
     * @inheritdocs
     */
    public function redirect()
    {
        return null;
    }

    /**
     * @inheritdocs
     */
    public function getData()
    {
        return '';
    }

}
