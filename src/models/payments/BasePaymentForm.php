<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\payments;

use craft\base\Model;
use craft\commerce\models\PaymentSource;
use yii\base\NotSupportedException;

/**
 * Class BasePaymentForm
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class BasePaymentForm extends Model
{
    /**
     * Populate the payment form from a payment form.
     *
     * @param PaymentSource $paymentSource the source to ue
     * @throws NotSupportedException if not supported by current gateway.
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource)
    {
        throw new NotSupportedException();
    }
}
