<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

/**
 * Payment Form helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class PaymentForm
{
    public const PAYMENT_FORM_NAMESPACE = 'paymentForm';

    /**
     * Generate the payment form namespace prefix.
     *
     * @param string $gatewayHandle
     * @return string
     */
    public static function getPaymentFormNamespace(string $gatewayHandle): string
    {
        return sprintf('%s[%s]', self::PAYMENT_FORM_NAMESPACE, $gatewayHandle);
    }

    /**
     * Generate the payment form namespace for retrieve request params.
     *
     * @param string $gatewayHandle
     * @return string
     */
    public static function getPaymentFormParamName(string $gatewayHandle): string
    {
        return sprintf('%s.%s', self::PAYMENT_FORM_NAMESPACE, $gatewayHandle);
    }
}
