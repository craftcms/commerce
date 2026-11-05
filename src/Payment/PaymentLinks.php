<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use CraftCms\Cms\Support\Config;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Activity\OrderPaymentLinkChanged;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Plugin;
use DateTimeImmutable;
use Illuminate\Container\Attributes\Singleton;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Hosted payment links that gateways create for an order.
 *
 * A link is a pending `purchase` transaction whose `response` holds the link’s URL and expiry. The gateway completes it through
 * its webhook, matching it by the transaction’s hash. Transactions are never edited, so a link ends with a failed child
 * row that has the code `expired`.
 *
 * @since 6.0.0
 */
#[Singleton]
class PaymentLinks
{
    public const string EXPIRED_CODE = 'expired';

    public function __construct(private readonly Transactions $transactions)
    {
    }

    /**
     * @throws PaymentException
     */
    public function create(Order $order, Gateway $gateway, ?float $amount = null): Transaction
    {
        if (!$order->id || !$order->isCompleted) {
            throw new PaymentException(t('Payment links can only be created for completed orders.', category: 'commerce'));
        }

        if (!$gateway->supportsCollectPaymentLink()) {
            throw new PaymentException(t('This gateway can’t create payment links.', category: 'commerce'));
        }

        $outstanding = Currency::round($order->getOutstandingBalance());

        if ($outstanding <= 0) {
            throw new PaymentException(t('This order has no outstanding balance.', category: 'commerce'));
        }

        $amount = Currency::round($amount ?? $outstanding);

        if ($amount <= 0 || $amount > $outstanding || ($amount < $outstanding && !$gateway->supportsPartialPayment())) {
            throw new PaymentException(t('The amount must be greater than zero and no more than {amount}.', ['amount' => Currency::formatAsCurrency($outstanding, $order->currency)], category: 'commerce'));
        }

        $expiresAt = now()->addSeconds(Config::durationInSeconds(Plugin::getInstance()->getSettings()->paymentLinkDuration))->toDateTimeImmutable();

        $transaction = new Transaction([
            'orderId' => $order->id,
            'gatewayId' => $gateway->id,
            'userId' => currentUserElement()?->id,
            'type' => TransactionRecord::TYPE_PURCHASE,
            'status' => TransactionRecord::STATUS_PENDING,
            'currency' => $order->currency,
            'paymentCurrency' => $order->currency,
            'paymentRate' => 1.0,
            'amount' => $amount,
            'paymentAmount' => $amount,
        ]);
        $transaction->setOrder($order);
        $transaction->setGateway($gateway);

        try {
            $link = $gateway->createCollectPaymentLink($transaction, $order, $expiresAt);
        } catch (Throwable $exception) {
            throw new PaymentException($exception->getMessage(), 0, $exception);
        }

        $transaction->reference = $link->reference;
        $transaction->response = json_encode(['paymentLink' => [
            'url' => $link->url,
            'expiresAt' => ($link->expiresAt ?? $expiresAt)->format(DATE_ATOM),
        ]]);

        if (!$this->transactions->saveTransaction($transaction)) {
            throw new PaymentException(t('Couldn’t create the payment link.', category: 'commerce'));
        }

        Activities::record(new OrderPaymentLinkChanged($order, OrderPaymentLinkChanged::CREATED, $transaction->getPaymentAmountAsCurrency(), $gateway->name));

        return $transaction;
    }

    /**
     * The links on the order that can still be paid.
     *
     * @return list<Transaction>
     */
    public function openLinks(Order $order): array
    {
        $transactions = $this->transactions->getAllTransactionsByOrderId((int)$order->id);
        $closed = array_filter(array_map(fn(Transaction $transaction) => $transaction->parentId, $transactions));

        return array_values(array_filter($transactions, fn(Transaction $transaction) => $this->isLink($transaction)
            && !in_array($transaction->id, $closed, true)
            && !$this->hasExpired($transaction)));
    }

    public function isLink(Transaction $transaction): bool
    {
        return $transaction->status === TransactionRecord::STATUS_PENDING
            && $transaction->type === TransactionRecord::TYPE_PURCHASE
            && $this->link($transaction) !== null;
    }

    /**
     * @return array{url: string, expiresAt: string|null}|null
     */
    public function link(Transaction $transaction): ?array
    {
        $response = $transaction->response;
        $response = is_string($response) ? json_decode($response, true) : $response;

        return is_array($response) && isset($response['paymentLink']['url']) ? $response['paymentLink'] : null;
    }

    public function hasExpired(Transaction $transaction): bool
    {
        $expiresAt = $this->link($transaction)['expiresAt'] ?? null;

        return $expiresAt !== null && new DateTimeImmutable($expiresAt) <= now();
    }

    /**
     * Cancels a link at the gateway where it can, and closes it locally so a late payment isn’t matched to it automatically.
     */
    public function expire(Transaction $link): void
    {
        $order = $link->getOrder();

        try {
            $link->getGateway()?->expireCollectPaymentLink($link);
        } catch (Throwable $exception) {
            report($exception);
        }

        $closing = new Transaction([
            'orderId' => $link->orderId,
            'parentId' => $link->id,
            'gatewayId' => $link->gatewayId,
            'type' => $link->type,
            'status' => TransactionRecord::STATUS_FAILED,
            'code' => self::EXPIRED_CODE,
            'message' => t('The payment link expired.', category: 'commerce'),
            'currency' => $link->currency,
            'paymentCurrency' => $link->paymentCurrency,
            'paymentRate' => $link->paymentRate,
            'amount' => $link->amount,
            'paymentAmount' => $link->paymentAmount,
            'reference' => $link->reference,
        ]);

        if ($order) {
            $closing->setOrder($order);
        }

        $this->transactions->saveTransaction($closing);

        if ($order) {
            Activities::record(new OrderPaymentLinkChanged($order, OrderPaymentLinkChanged::EXPIRED, $link->getPaymentAmountAsCurrency(), $link->getGateway()?->name));
        }
    }

    /**
     * Expires every open link on the order, for when a change to the order means the amounts no longer match.
     */
    public function expireOpenLinks(Order $order): void
    {
        foreach ($this->openLinks($order) as $link) {
            $this->expire($link);
        }
    }

    public function url(Transaction $transaction): ?string
    {
        return $this->link($transaction)['url'] ?? null;
    }
}
