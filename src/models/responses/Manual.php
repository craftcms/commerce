<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * This is a dummy gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Manual implements RequestResponseInterface
{
    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return date('Y-m-d-H-i-s');
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function redirect()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return false;
    }
}
