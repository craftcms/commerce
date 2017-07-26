<?php

namespace craft\commerce\models\payments;

/**
 * Stripe Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Gateways\PaymentFormModels\
 * @since     1.1
 */
class StripePaymentForm extends CreditCardPaymentForm
{
    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        parent::setAttributes($values, $safeOnly);

        if (isset($values['stripeToken'])) {
            $this->token = $values['stripeToken'];
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        if (empty($this->token)) {
            return parent::rules();
        }

        return [];
    }
}