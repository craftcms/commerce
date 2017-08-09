<?php

namespace craft\commerce\gateways;

use craft\base\MissingComponentInterface;
use craft\base\MissingComponentTrait;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\Transaction;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;

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

    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    protected function getRequest(Transaction $transaction, BasePaymentForm $form)
    {
    }

    protected function prepareCaptureRequest($request, string $reference)
    {
    }

    protected function prepareRefundRequest($request, string $reference)
    {
    }

    /**
     * @inheritdoc
     */
    protected function preparePurchaseRequest($request)
    {
    }

    /**
     * @inheritdoc
     */
    protected function prepareAuthorizeRequest($request)
    {
    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse($response): RequestResponseInterface
    {
    }

    /**
     * @inheritdoc
     */
    protected function sendRequest($request)
    {
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel()
    {
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
    public function supportsPurchase(): bool
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
    public function supportsRefund(): bool
    {
        return false;
    }
}
