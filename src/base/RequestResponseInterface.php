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
     * Returns whether or not the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Returns whether or not the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool;

    /**
     * Returns whether or not the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool;

    /**
     * Returns the redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string;

    /**
     * Returns the redirect data provided.
     *
     * @return array
     */
    public function getRedirectData(): array;

    /**
     * Returns the redirect URL to use, if any.
     *
     * @return string
     */
    public function getRedirectUrl(): string;

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string;

    /**
     * Returns the response code.
     *
     * @return string
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
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect();
}
