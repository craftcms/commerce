<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\base\DummyRequestResponse;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\helpers\StringHelper;
use craft\web\Response as WebResponse;
use yii\web\UnauthorizedHttpException;

/**
 * Dummy represents a dummy gateway.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Dummy extends Gateway
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): OffsitePaymentForm
    {
        return new OffsitePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource($sourceData): PaymentSource
    {
        $paymentSource = new PaymentSource();
        $paymentSource->gatewayId = $this->id;
        $paymentSource->token = StringHelper::randomString();
        $paymentSource->response = '';
        $paymentSource->description = 'Dummy source';

        return $paymentSource;
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }
}
