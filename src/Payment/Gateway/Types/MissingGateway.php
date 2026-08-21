<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Types;

use CraftCms\Cms\Component\Concerns\MissingComponentTrait;
use CraftCms\Cms\Component\Contracts\MissingComponentInterface;
use CraftCms\Commerce\Exceptions\NotImplementedException;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Models\PaymentSource;
use CraftCms\Commerce\Payment\Models\Transaction;
use Illuminate\Http\Response;
use Override;

/**
 * MissingGateway represents a gateway with an invalid class.
 */
class MissingGateway extends Gateway implements MissingComponentInterface
{
    use MissingComponentTrait;

    public function __set(string $name, mixed $value): void
    {
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function getPaymentFormModel(): BasePaymentForm
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function deletePaymentSource(string $token): bool
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function processWebHook(): Response
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException();
    }

    #[Override]
    public function supportsAuthorize(): bool
    {
        return false;
    }

    #[Override]
    public function supportsCapture(): bool
    {
        return false;
    }

    #[Override]
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    #[Override]
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPurchase(): bool
    {
        return false;
    }

    #[Override]
    public function supportsRefund(): bool
    {
        return false;
    }

    #[Override]
    public function supportsWebhooks(): bool
    {
        return false;
    }

    #[Override]
    public function supportsPartialRefund(): bool
    {
        return false;
    }
}
