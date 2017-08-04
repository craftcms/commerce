<?php

namespace craft\commerce\base;

use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;

/**
 * This is an abstract class to be used by offsite gateways
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
abstract class OffsiteGateway extends Gateway
{
    /**
     * @inheritdoc
     */
    public function getPaymentFormModel()
    {
        return new OffsitePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function populateCard($card, CreditCardPaymentForm $paymentForm)
    {
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(AbstractRequest $request, BasePaymentForm $paymentForm)
    {
    }
}
