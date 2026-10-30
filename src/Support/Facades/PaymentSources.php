<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllPaymentSourcesByCustomerId(int|null $customerId = null, int|null $gatewayId = null)
 * @method static \Illuminate\Support\Collection getAllPaymentSourcesByGatewayId(int|null $gatewayId = null)
 * @method static \Illuminate\Support\Collection getAllGatewayPaymentSourcesByCustomerId(int|null $gatewayId = null, int|null $customerId = null)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentSource|null getPaymentSourceByTokenAndGatewayId(string $token, int $gatewayId)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentSource|null getPaymentSourceById(int $sourceId)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentSource|null getPaymentSourceByIdAndUserId(int $sourceId, int $userId)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentSource createPaymentSource(int $customerId, \CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface $gateway, \CraftCms\Commerce\Payment\Forms\BasePaymentForm $paymentForm, string|null $sourceDescription = null, bool $makePrimarySource = false)
 * @method static bool savePaymentSource(\CraftCms\Commerce\Payment\Data\PaymentSource $paymentSource, bool $runValidation = true)
 * @method static bool deletePaymentSourceById(int $id)
 *
 * @see \CraftCms\Commerce\Payment\PaymentSources
 */
class PaymentSources extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\PaymentSources::class;
    }
}
