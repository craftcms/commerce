<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Contracts;

use CraftCms\Cms\Component\Contracts\SavableComponentInterface;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\CollectPaymentLink;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use DateTimeInterface;
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

    /**
     * @param array<string, mixed> $params
     */
    public function paymentFormUi(BasePaymentForm $form, array $params = []): ?Ui;

    /**
     * @param array<string, mixed> $params
     */
    public function paymentConfirmationFormUi(array $params = []): ?Ui;

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

    /**
     * Whether the gateway can create a hosted payment page for an order that a customer pays on.
     *
     * The gateway completes the transaction through its webhook, matching it by {@see Transaction::$hash}.
     */
    public function supportsCollectPaymentLink(): bool;

    /**
     * Creates a hosted payment page for the pending transaction. The transaction isn’t saved yet; use its `hash` to match the webhook.
     *
     * @param DateTimeInterface $expiresAt When the link should stop working, where the gateway supports expiry.
     */
    public function createCollectPaymentLink(Transaction $transaction, Order $order, DateTimeInterface $expiresAt): CollectPaymentLink;

    /**
     * Cancels a hosted payment page, so it can no longer be paid. Returns false when the gateway can’t cancel it.
     */
    public function expireCollectPaymentLink(Transaction $transaction): bool;

    /**
     * The URL of the gateway’s own virtual terminal, where staff can take a payment for the order, or null if there isn’t one.
     */
    public function getVirtualTerminalUrl(Order $order): ?string;
}
