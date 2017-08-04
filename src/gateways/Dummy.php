<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\base\CreditCardGatewayTrait;
use craft\commerce\base\Gateway;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Omnipay;

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

    // TODO none of this is good now.

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Dummy gateway');
    }

    public function populateCard($card, CreditCardPaymentForm $paymentForm)
    {
        // TODO: Implement populateCard() method.
    }

    public function populateRequest(AbstractRequest $request, BasePaymentForm $form)
    {
        // TODO: Implement populateRequest() method.
    }

    protected function gateway()
    {
        // TODO: Implement gateway() method.
    }



    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var AbstractGateway $gateway */
        $gateway = Omnipay::create($this->getGatewayClassName());

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return '\\'.Gateway::class;
    }
}
