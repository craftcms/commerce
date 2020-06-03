<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;
use Throwable;

/**
 * GatewayInterface defines the common interface to be implemented by gateway classes.
 *
 * A class implementing this interface should also use [[SavableComponentTrait]] and [[GatewayTrait]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @mixin GatewayTrait
 */
interface GatewayInterface extends SavableComponentInterface
{
    /**
     * Makes an authorize request.
     *
     * @param Transaction $transaction The authorize transaction
     * @param BasePaymentForm $form A form filled with payment info
     * @return RequestResponseInterface
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    /**
     * Makes a capture request.
     *
     * @param Transaction $transaction The capture transaction
     * @param string $reference Reference for the transaction being captured.
     * @return RequestResponseInterface
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface;

    /**
     * Complete the authorization for offsite payments.
     *
     * @param Transaction $transaction The transaction
     * @return RequestResponseInterface
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface;

    /**
     * Complete the purchase for offsite payments.
     *
     * @param Transaction $transaction The transaction
     * @return RequestResponseInterface
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface;

    /**
     * Creates a payment source from source data and user id.
     *
     * @param BasePaymentForm $sourceData
     * @param int $userId
     * @return PaymentSource
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource;

    /**
     * Deletes a payment source on the gateway by its token.
     *
     * @param string $token
     * @return bool
     */
    public function deletePaymentSource($token): bool;

    /**
     * Returns payment form model to use in payment forms.
     *
     * @return BasePaymentForm
     */
    public function getPaymentFormModel(): BasePaymentForm;

    /**
     * Makes a purchase request.
     *
     * @param Transaction $transaction The purchase transaction
     * @param BasePaymentForm $form A form filled with payment info
     * @return RequestResponseInterface
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    /**
     * Makes an refund request.
     *
     * @param Transaction $transaction The refund transaction
     * @return RequestResponseInterface
     */
    public function refund(Transaction $transaction): RequestResponseInterface;

    /**
     * Processes a webhook and return a response
     *
     * @return WebResponse
     * @throws Throwable if something goes wrong
     */
    public function processWebHook(): WebResponse;

    /**
     * Returns true if gateway supports authorize requests.
     *
     * @return bool
     */
    public function supportsAuthorize(): bool;

    /**
     * Returns true if gateway supports capture requests.
     *
     * @return bool
     */
    public function supportsCapture(): bool;

    /**
     * Returns true if gateway supports completing authorize requests
     *
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool;

    /**
     * Returns true if gateway supports completing purchase requests
     *
     * @return bool
     */
    public function supportsCompletePurchase(): bool;

    /**
     * Returns true if gateway supports storing payment sources
     *
     * @return bool
     */
    public function supportsPaymentSources(): bool;

    /**
     * Returns true if gateway supports purchase requests.
     *
     * @return bool
     */
    public function supportsPurchase(): bool;

    /**
     * Returns true if gateway supports refund requests.
     *
     * @return bool
     */
    public function supportsRefund(): bool;

    /**
     * Returns true if gateway supports partial refund requests.
     *
     * @return bool
     */
    public function supportsPartialRefund(): bool;

    /**
     * Returns true if gateway supports webhooks.
     *
     * If `true` is returned, this show the webhook url
     * to the person setting up your gateway (after the gateway is saved).
     * This also affects whether the webhook controller should route webhook requests to your
     * `processWebHook()` method in this class.
     *
     * @return bool
     */
    public function supportsWebhooks(): bool;

    /**
     * Returns true if gateway supports payments for the supplied order.
     *
     * This method is called before a payment is made for the supplied order. It can be
     * used by developers building a checkout and deciding if this gateway should be shown as
     * and option to the customer.
     *
     * It also can prevent a gateway from being used with a particular order.
     *
     * An example of this can be found in the manual payment gateway: It has a setting that can limit it's use
     * to only be used with orders that are of a zero value amount. See below for an example of how it uses this
     * method to reject the gateway's use on orders that are not $0.00 if the setting is turned on
     *
     * ```php
     * public function availableForUseWithOrder($order): bool
     *  if ($this->onlyAllowForZeroPriceOrders && $order->getTotalPrice() != 0) {
     *    return false;
     *  }
     * return true;
     * }
     * ```
     *
     * @param $order Order The order this gateway can or can not be available for payment with.
     * @return bool
     */
    public function availableForUseWithOrder(Order $order): bool;
}
