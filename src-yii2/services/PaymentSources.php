<?php

namespace craft\commerce\services;

use craft\commerce\models\payments\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Models\PaymentSource;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\PaymentSources::class)` instead.
 */
class PaymentSources extends Component
{
    public const EVENT_DELETE_PAYMENT_SOURCE = \CraftCms\Commerce\Services\PaymentSources::EVENT_DELETE_PAYMENT_SOURCE;

    public const EVENT_BEFORE_SAVE_PAYMENT_SOURCE = \CraftCms\Commerce\Services\PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE;

    public const EVENT_AFTER_SAVE_PAYMENT_SOURCE = \CraftCms\Commerce\Services\PaymentSources::EVENT_AFTER_SAVE_PAYMENT_SOURCE;

    /**
     * @return Collection<int, PaymentSource>
     */
    public function getAllPaymentSourcesByCustomerId(?int $customerId = null, ?int $gatewayId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getAllPaymentSourcesByCustomerId($customerId, $gatewayId);
    }

    /**
     * @return Collection<int, PaymentSource>
     */
    public function getAllPaymentSourcesByGatewayId(?int $gatewayId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getAllPaymentSourcesByGatewayId($gatewayId);
    }

    /**
     * @return Collection<int, PaymentSource>
     */
    public function getAllGatewayPaymentSourcesByCustomerId(?int $gatewayId = null, ?int $customerId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getAllGatewayPaymentSourcesByCustomerId($gatewayId, $customerId);
    }

    public function getPaymentSourceByTokenAndGatewayId(string $token, int $gatewayId): ?PaymentSource
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getPaymentSourceByTokenAndGatewayId($token, $gatewayId);
    }

    public function getPaymentSourceById(int $sourceId): ?PaymentSource
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getPaymentSourceById($sourceId);
    }

    public function getPaymentSourceByIdAndUserId(int $sourceId, int $userId): ?PaymentSource
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->getPaymentSourceByIdAndUserId($sourceId, $userId);
    }

    public function createPaymentSource(int $customerId, GatewayInterface $gateway, BasePaymentForm $paymentForm, ?string $sourceDescription = null, bool $makePrimarySource = false): PaymentSource
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->createPaymentSource($customerId, $gateway, $paymentForm, $sourceDescription, $makePrimarySource);
    }

    public function savePaymentSource(PaymentSource $paymentSource, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->savePaymentSource($paymentSource, $runValidation);
    }

    public function deletePaymentSourceById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\PaymentSources::class)->deletePaymentSourceById($id);
    }
}
