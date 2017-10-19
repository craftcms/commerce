<?php

namespace craft\commerce\gateways;

use craft\commerce\base\DummyRequestResponse;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;

/**
 * Dummy represents a dummy gateway.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Dummy extends Gateway
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $params
     *
     * @return string
     */
    public function getPaymentFormHtml(array $params)
    {
        return '';
    }

    /**
     * @return OffsitePaymentForm
     */
    public function getPaymentFormModel()
    {
        return new OffsitePaymentForm();
    }

    /**
     * @param Transaction     $transaction
     * @param BasePaymentForm $form
     *
     * @return RequestResponseInterface
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @param Transaction $transaction
     * @param string      $reference
     *
     * @return RequestResponseInterface
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @param Transaction $transaction
     *
     * @return RequestResponseInterface
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @param Transaction $transaction
     *
     * @return RequestResponseInterface
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @param Transaction     $transaction
     * @param BasePaymentForm $form
     *
     * @return RequestResponseInterface
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @return WebResponse
     */
    public function processWebHook(): WebResponse
    {
        return null;
    }

    /**
     * @param Transaction $transaction
     * @param string      $reference
     *
     * @return RequestResponseInterface
     */
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @return bool
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }
}
