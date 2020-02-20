<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gateways;

use craft\base\MissingComponentInterface;
use craft\base\MissingComponentTrait;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;
use yii\base\NotSupportedException;

/**
 * MissingGateway represents a gateway with an invalid class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class MissingGateway extends Gateway implements MissingComponentInterface
{
    use MissingComponentTrait;


    public function __set($name, $value)
    {
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return false;
    }
}
