<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * This interface class functions that a Commerce Payment needs to implement.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface RequestResponseInterface
{
    /**
     * Returns whether the payment was successful.
     */
    public function isSuccessful(): bool;

    /**
     * Returns whether the payment is being processed by gateway.
     */
    public function isProcessing(): bool;

    /**
     * Returns whether the user needs to be redirected.
     */
    public function isRedirect(): bool;

    /**
     * Returns the redirect method to use, if any.
     */
    public function getRedirectMethod(): string;

    /**
     * Returns the redirect data provided.
     */
    public function getRedirectData(): array;

    /**
     * Returns the redirect URL to use, if any.
     */
    public function getRedirectUrl(): string;

    /**
     * Returns the transaction reference.
     */
    public function getTransactionReference(): string;

    /**
     * Returns the response code.
     */
    public function getCode(): string;

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Returns the gateway message.
     */
    public function getMessage(): string;

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect();
}
