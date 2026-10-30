<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Contracts;

use CraftCms\Cms\Component\Contracts\SavableComponentInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use Illuminate\Http\Response;
use Throwable;

interface GatewayInterface extends SavableComponentInterface
{
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface;

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface;

    public function completePurchase(Transaction $transaction): RequestResponseInterface;

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource;

    public function deletePaymentSource(string $token): bool;

    public function getPaymentFormModel(): BasePaymentForm;

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface;

    public function refund(Transaction $transaction): RequestResponseInterface;

    /**
     * @throws Throwable
     */
    public function processWebHook(): Response;

    public function supportsAuthorize(): bool;

    public function supportsCapture(): bool;

    public function supportsCompleteAuthorize(): bool;

    public function supportsCompletePurchase(): bool;

    public function supportsPaymentSources(): bool;

    public function supportsPurchase(): bool;

    public function supportsRefund(): bool;

    public function supportsPartialRefund(): bool;

    public function supportsPartialPayment(): bool;

    public function supportsWebhooks(): bool;

    public function availableForUseWithOrder(Order $order): bool;

    public function getTransactionHashFromWebhook(): ?string;
}
