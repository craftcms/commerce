<?php

namespace craft\commerce\gateways\base;

use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use Omnipay\Common\CreditCard;
use Omnipay\Manual\Message\Request;

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
abstract class OffsiteGateway extends BaseGateway
{
    /**
     * @inheritdoc
     */
    public function requiresCreditCard()
    {
        return false;
    }

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
    public function populateCard(CreditCard $card, CreditCardPaymentForm $paymentForm)
    {
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(Request $request, BasePaymentForm $paymentForm)
    {
    }
}
