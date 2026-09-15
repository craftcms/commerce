<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

class PaymentForm
{
    public const PAYMENT_FORM_NAMESPACE = 'paymentForm';

    public static function getPaymentFormNamespace(string $gatewayHandle): string
    {
        return sprintf('%s[%s]', self::PAYMENT_FORM_NAMESPACE, $gatewayHandle);
    }

    public static function getPaymentFormParamName(string $gatewayHandle): string
    {
        return sprintf('%s.%s', self::PAYMENT_FORM_NAMESPACE, $gatewayHandle);
    }
}
