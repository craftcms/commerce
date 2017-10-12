<?php

namespace craft\commerce\gateways;

use craft\base\MissingComponentInterface;
use craft\base\MissingComponentTrait;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;

/**
 * MissingGateway represents a gateway with an invalid class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 *
 * @property null $paymentFormModel
 * @property null $gatewayClassName
 */
class MissingGateway extends Gateway implements MissingComponentInterface
{
    // Traits
    // =========================================================================

    use MissingComponentTrait;

    /**
     * @param array $params
     *
     * @return null|string|void
     */
    public function getPaymentFormHtml(array $params)
    {
    }

    /**
     *
     */
    public function getPaymentFormModel()
    {
    }

    /**
     * @param Transaction     $transaction
     * @param BasePaymentForm $form
     *
     * @return RequestResponseInterface
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    /**
     * @param Transaction $transaction
     * @param string      $reference
     *
     * @return RequestResponseInterface
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    /**
     * @param Transaction $transaction
     *
     * @return RequestResponseInterface
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
    }

    /**
     * @param Transaction $transaction
     *
     * @return RequestResponseInterface
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
    }

    /**
     * @param Transaction     $transaction
     * @param BasePaymentForm $form
     *
     * @return RequestResponseInterface
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    /**
     * @return WebResponse
     */
    public function processWebHook(): WebResponse
    {
    }

    /**
     * @param Transaction $transaction
     * @param string      $reference
     *
     * @return RequestResponseInterface
     */
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    /**
     * @return bool
     */
    public function supportsAuthorize(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsCapture(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsPurchase(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }
}
