<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\base\CreditCardGatewayTrait;
use craft\commerce\base\DummyRequestResponse;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
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
    use CreditCardGatewayTrait;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Dummy gateway');
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
    public function supportsAuthorize(): bool
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
    public function supportsCapture(): bool
    {
        return true;
    }
    
    /**
     * @inheritdoc
     */
    protected function getRequest(Transaction $transaction, BasePaymentForm $form)
    {
    }

    /**
     * @inheritdoc
     */
    protected function prepareCaptureRequest($request, string $reference)
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
    protected function preparePurchaseRequest($request)
    {
    }

    /**
     * @inheritdoc
     */
    protected function prepareRefundRequest($request, string $reference)
    {
    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse($response): RequestResponseInterface
    {
        return new DummyRequestResponse();
    }

    /**
     * @inheritdoc
     */
    protected function sendRequest($request)
    {
    }
}
