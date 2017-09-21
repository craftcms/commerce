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


    public function getPaymentFormHtml(array $params)
    {
    }

    public function getPaymentFormModel()
    {
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    public function processWebHook(): WebResponse
    {
    }
    
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    public function supportsAuthorize(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }
}
