<?php

namespace craft\commerce\base;

/**
 * Payment Response Interface
 *
 * This interface class functions that a Commerce Payment needs to implement.
 */
interface RequestResponseInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Whether or not the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Whether or not the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool;

    /**
     * Whether or not the user needs to be redirected.
     *
     * @return bool
     */
    public function isRedirect(): bool;

    /**
     * The redirect method to use, if any.
     *
     * @return string
     */
    public function getRedirectMethod(): string;

    /**
     * The redirect data provided.
     *
     * @return array
     */
    public function getRedirectData(): array;

    /**
     * The redirect URL to use, if any.
     *
     * @return string
     */
    public function getRedirectUrl(): string;

    /**
     * Get the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string;

    /**
     * Get the response code.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Get the data.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Get the gateway message.
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
