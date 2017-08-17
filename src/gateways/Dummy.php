<?php

namespace craft\commerce\gateways;

use craft\commerce\base\DummyRequestResponse;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\Transaction;

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

    public function getPaymentFormHtml(array $params)
    {
    }

    public function getPaymentFormModel()
    {
        return new CreditCardPaymentForm();
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    public function supportsAuthorize(): bool
    {
        return true;
    }

    public function supportsCapture(): bool
    {
        return true;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    public function supportsPurchase(): bool
    {
        return true;
    }

    public function supportsRefund(): bool
    {
        return true;
    }
}
