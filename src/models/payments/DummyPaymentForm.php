<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\payments;

use craft\commerce\models\PaymentSource;

/**
 * Credit Card Payment form model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DummyPaymentForm extends CreditCardPaymentForm
{
    /**
     * @param PaymentSource $paymentSource
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource)
    {
        $this->token = $paymentSource->id;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        if ($this->token) {
            return []; //No validation of form if using a token
        }

        return parent::defineRules();
    }
}
