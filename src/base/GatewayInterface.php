<?php

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;

/**
 * GatewayInterface defines the common interface to be implemented by gateway classes.
 *
 * A class implementing this interface should also use [[SavableComponentTrait]] and [[GatewayTrait]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface GatewayInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Is the gateway enabled
     *
     * @return bool
     */
    public function getIsEnabled() : bool;

    /**
     * Make an authorize request.
     *
     * @param Transaction     $transaction The authorize transaction
     * @param BasePaymentForm $form        A form filled with payment info
     *
     * @return RequestResponseInterface
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    /**
     * Make a capture request.
     *
     * @param Transaction $transaction The capture transaction
     * @param string      $reference   Reference for the transaction being captured.
     *
     * @return RequestResponseInterface
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface;

    /**
     * Make a complete authorize request.
     *
     * @param Transaction $transaction The transaction
     *
     * @return RequestResponseInterface
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface;

    /**
     * Make a complete purchase request.
     *
     * @param Transaction $transaction The transaction
     *
     * @return RequestResponseInterface
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface;

    /**
     * Make a purchase request.
     *
     * @param Transaction     $transaction The purchase transaction
     * @param BasePaymentForm $form        A form filled with payment info
     *
     * @return RequestResponseInterface
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    /**
     * Make an refund request.
     *
     * @param Transaction $transaction The refund transaction
     * @param string      $reference   Reference for the transaction being refunded.
     *
     * @return RequestResponseInterface
     */
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface;

    /**
     * Process a webhook and return a response
     *
     * @return WebResponse
     */
    public function processWebHook(): WebResponse;

    /**
     * Return true if gateway supports authorize requests.
     *
     * @return bool
     */
    public function supportsAuthorize(): bool;

    /**
     * Return true if gateway supports capture requests.
     *
     * @return bool
     */
    public function supportsCapture(): bool;

    /**
     * Return true if gateway supports completing authorize requests
     *
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool;

    /**
     * Return true if gateway supports completing purchase requests
     *
     * @return bool
     */
    public function supportsCompletePurchase(): bool;

    /**
     * Return true if gateway supports purchase requests.
     *
     * @return bool
     */
    public function supportsPurchase(): bool;

    /**
     * Return true if gateway supports refund requests.
     *
     * @return bool
     */
    public function supportsRefund(): bool;

    /**
     * Return true if gateway supports webhooks.
     *
     * @return bool
     */
    public function supportsWebhooks(): bool;
}
