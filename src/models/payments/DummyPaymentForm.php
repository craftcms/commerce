<?php

namespace craft\commerce\models\payments;

use craft\commerce\models\PaymentSource;

/**
 * Credit Card Payment form model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class DummyPaymentForm extends BasePaymentForm
{
    /**
     * @param PaymentSource $paymentSource
     *
     * @return void
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource) {
    }
}
