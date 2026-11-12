<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Ui\Controls\Checkbox;
use CraftCms\Cms\Ui\Controls\Money as MoneyControl;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Exceptions\ManualTransactionException;
use CraftCms\Commerce\Payment\ManualTransactions;
use CraftCms\Commerce\Payment\Transactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The screens a payment is recorded, refunded or inspected from on the order edit page.
 *
 * Staff don’t enter card details in the control panel. A payment is taken in the gateway’s own tools and
 * either completes through the gateway’s webhook or is recorded here by hand.
 *
 * Recording and refunding act on the order as it is saved, so each refuses while the order has an edit
 * session with changes waiting. See {@see OrderEditGuard}.
 *
 * @since 6.0.0
 */
class OrderPaymentController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly OrderEditGuard $guard,
        private readonly UiResolver $uiResolver,
        private readonly ManualTransactions $manualTransactions,
    ) {
    }

    /**
     * The details of a transaction, for a read-only dialog.
     */
    public function transaction(Request $request): JsonResponse
    {
        $transaction = $this->transactionFor($request);
        $order = $transaction->getOrder();
        abort_unless($order && $order->canView($this->user()), 403, 'User not authorized to view this order.');

        $rows = array_filter([
            [t('ID', category: 'commerce'), (string)$transaction->id],
            [t('Reference', category: 'commerce'), $transaction->reference],
            [t('Hash', category: 'commerce'), $transaction->hash],
            [t('Gateway', category: 'commerce'), $transaction->getGateway()?->name],
            [t('Source', category: 'commerce'), $transaction->isManual() ? t('Recorded manually', category: 'commerce') : null],
            [t('Type', category: 'commerce'), t(ucfirst((string)$transaction->type), category: 'commerce')],
            [t('Status', category: 'commerce'), t(ucfirst((string)$transaction->status), category: 'commerce')],
            [t('Amount', category: 'commerce'), $transaction->getAmountAsCurrency()],
            [t('Payment Amount', category: 'commerce'), $this->paymentAmountWithRate($transaction)],
            [t('Code', category: 'commerce'), $transaction->code],
            [t('Message', category: 'commerce'), $transaction->message],
            [t('Note', category: 'commerce'), $transaction->note],
        ], fn(array $row) => $row[1] !== null && $row[1] !== '');

        $response = $transaction->response;

        return new JsonResponse([
            'title' => t('Transaction Details', category: 'commerce'),
            'rows' => array_map(fn(array $row) => ['label' => $row[0], 'value' => $row[1]], array_values($rows)),
            'response' => $response === null || $response === ''
                ? null
                : (string)json_encode(is_string($response) ? (json_decode($response, true) ?? $response) : $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * The UI of the gateway refund form, in the transaction’s payment currency, for `openUiModal()`.
     *
     * It submits to `orders/transaction-refund`.
     */
    public function refundForm(Request $request): JsonResponse
    {
        $transaction = $this->transactionFor($request);
        $order = $transaction->getOrder();
        abort_unless($order && $order->canView($this->user()), 403, 'User not authorized to view this order.');
        $this->abortIfEditing($order);
        abort_unless($transaction->canRefund(), 422, t('Couldn’t refund transaction.', category: 'commerce'));

        $ui = Ui::make([
            Field::make(
                t('Amount', category: 'commerce'),
                MoneyControl::make('amount')->currency((string)$transaction->paymentCurrency)->max($transaction->getRefundableAmount())->size(12),
            )->instructions($this->refundNote($transaction))->required(),
            Field::make(t('Note', category: 'commerce'), Textarea::make('note'))->required(),
        ]);

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($ui, new UiContext(values: [
                'amount' => ['value' => $transaction->getRefundableAmount(), 'locale' => I18N::getFormattingLocale()->id],
                'note' => '',
            ])),
            'title' => t('Refund', category: 'commerce'),
            'submitLabel' => t('Refund', category: 'commerce'),
        ]);
    }

    /**
     * The UI of the form that records a payment received outside Commerce, for `openUiModal()`.
     */
    public function recordPaymentForm(Request $request): JsonResponse
    {
        $order = $this->recordableOrder($request);
        $ui = Ui::make([
            Callout::make('external-payment-notice', t('This doesn’t charge the customer. Only record money you’ve already received outside the gateway.'))
                ->variant('warning'),
            Field::make(t('Amount', category: 'commerce'), MoneyControl::make('amount')->currency($order->currency)->size(12))
                ->instructions($this->outstandingNote($order))
                ->required(),
            Field::make(t('Reference', category: 'commerce'), Text::make('reference')),
            Field::make(t('Note', category: 'commerce'), Textarea::make('note')),
            Field::make(t('Confirm', category: 'commerce'), Checkbox::make('confirmed')->label(t('Record more than the outstanding balance', category: 'commerce')))
                ->instructions(t('Only needed when the amount is more than what is outstanding.', category: 'commerce')),
        ]);

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($ui, new UiContext(values: [
                'amount' => ['value' => max(0, $order->getOutstandingBalance()), 'locale' => I18N::getFormattingLocale()->id],
                'reference' => '',
                'note' => '',
                'confirmed' => false,
            ])),
            'title' => t('Record External Payment', category: 'commerce'),
            'submitLabel' => t('Record', category: 'commerce'),
        ]);
    }

    /**
     * Records a payment received outside Commerce.
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $order = $this->recordableOrder($request);
        $data = $this->validated($request);
        $amount = $this->amount($data['amount'], $order->currency);

        if ($amount > Currency::round(max(0.0, $order->getOutstandingBalance())) && !$request->boolean('confirmed')) {
            $message = t('Confirm that you want to record more than the outstanding balance.', category: 'commerce');

            return new JsonResponse(['message' => $message, 'errors' => ['confirmed' => [$message]]], 422);
        }

        try {
            $transaction = $this->manualTransactions->recordPayment(
                $order,
                $amount,
                $data['reference'] ?? null,
                $data['note'] ?? null,
            );
        } catch (ManualTransactionException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return $this->asJsonSuccess(t('Payment recorded.', category: 'commerce'), ['transactionId' => $transaction->id]);
    }

    /**
     * The UI of the form that records a refund made outside Commerce, for `openUiModal()`.
     *
     * Given a transaction, the refund is linked to it. A refund linked to a gateway payment needs confirming, because the gateway
     * never returns the money.
     */
    public function recordRefundForm(Request $request): JsonResponse
    {
        $order = $this->recordableOrder($request);
        $parent = $this->parentFor($request, $order);
        $maximum = $this->manualTransactions->maximumRefund($order, $parent);
        abort_unless($maximum > 0, 422, t('There is nothing left to refund.', category: 'commerce'));

        $nodes = [
            Callout::make('external-refund-notice', t('This doesn’t refund the customer. Only record money you’ve already returned outside the gateway.'))
                ->variant('warning'),
            Field::make(t('Amount', category: 'commerce'), MoneyControl::make('amount')->currency($order->currency)->max($maximum)->size(12))
                ->instructions(t('Up to {amount} can be refunded.', ['amount' => Currency::formatAsCurrency($maximum, $order->currency)], category: 'commerce'))
                ->required(),
            Field::make(t('Reference', category: 'commerce'), Text::make('reference')),
            Field::make(t('Note', category: 'commerce'), Textarea::make('note'))->required(),
        ];

        if ($parent && !$parent->isManual()) {
            $nodes[] = Field::make(
                t('Confirm', category: 'commerce'),
                Checkbox::make('confirmed')->label(t('The money was returned outside {gateway}, so the gateway won’t refund it.', ['gateway' => $parent->getGateway()->name ?? t('the gateway', category: 'commerce')], category: 'commerce')),
            )->required();
        }

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve(Ui::make($nodes), new UiContext(values: [
                'amount' => ['value' => $maximum, 'locale' => I18N::getFormattingLocale()->id],
                'reference' => '',
                'note' => '',
                'confirmed' => false,
            ])),
            'title' => t('Record External Refund', category: 'commerce'),
            'submitLabel' => t('Record', category: 'commerce'),
        ]);
    }

    /**
     * Records a refund made outside Commerce.
     */
    public function recordRefund(Request $request): JsonResponse
    {
        $order = $this->recordableOrder($request);
        $parent = $this->parentFor($request, $order);
        $data = $this->validated($request, noteRequired: true);

        if ($parent && !$parent->isManual() && !$request->boolean('confirmed')) {
            return new JsonResponse([
                'message' => t('Confirm the refund was made outside the gateway.', category: 'commerce'),
                'errors' => ['confirmed' => [t('Confirm the refund was made outside the gateway.', category: 'commerce')]],
            ], 422);
        }

        try {
            $transaction = $this->manualTransactions->recordRefund(
                $order,
                $this->amount($data['amount'], $order->currency),
                $data['reference'] ?? null,
                $data['note'] ?? null,
                $parent,
            );
        } catch (ManualTransactionException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return $this->asJsonSuccess(t('Refund recorded.', category: 'commerce'), ['transactionId' => $transaction->id]);
    }

    /**
     * @return array{amount: array<string, mixed>|string, reference?: string|null, note?: string|null}
     */
    private function validated(Request $request, bool $noteRequired = false): array
    {
        /** @var array{amount: array<string, mixed>|string, reference?: string|null, note?: string|null} */
        return $request->validate([
            'amount' => ['required'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => [$noteRequired ? 'required' : 'nullable', 'string'],
        ]);
    }

    /**
     * @param array<string, mixed>|string $input
     */
    private function amount(array|string $input, string $currency): float
    {
        $input = is_array($input) ? $input : ['value' => $input, 'locale' => I18N::getFormattingLocale()->id];

        return (float)Money::toDecimal(Money::toMoney([...$input, 'currency' => $currency]));
    }

    private function outstandingNote(Order $order): string
    {
        return $order->hasOutstandingBalance()
            ? t('{amount} is outstanding.', ['amount' => $order->outstandingBalanceAsCurrency], category: 'commerce')
            : t('This order has no outstanding balance.', category: 'commerce');
    }

    private function paymentAmountWithRate(Transaction $transaction): string
    {
        $amount = $transaction->getPaymentAmountAsCurrency();

        if ($transaction->paymentCurrency === $transaction->currency) {
            return $amount;
        }

        return t('{amount} (1 {currency} = {rate} {paymentCurrency})', [
            'amount' => $amount,
            'currency' => $transaction->currency,
            'rate' => $transaction->paymentRate,
            'paymentCurrency' => $transaction->paymentCurrency,
        ], category: 'commerce');
    }

    private function refundNote(Transaction $transaction): string
    {
        $message = t('Up to {amount} can be refunded.', ['amount' => $transaction->getRefundableAmountAsCurrency()], category: 'commerce');

        return $transaction->paymentCurrency === $transaction->currency
            ? $message
            : $message . ' ' . t('Refunds are made in {currency}.', ['currency' => $transaction->paymentCurrency], category: 'commerce');
    }

    private function parentFor(Request $request, Order $order): ?Transaction
    {
        if (!$request->filled('id')) {
            return null;
        }

        $parent = app(Transactions::class)->getTransactionById((int)$request->input('id'));
        abort_if(!$parent || $parent->orderId !== $order->id, 404, t('Can not find the transaction.', category: 'commerce'));
        abort_unless($parent->status === 'success' && in_array($parent->type, ['purchase', 'capture'], true), 422, t('Couldn’t refund transaction.', category: 'commerce'));

        return $parent;
    }

    private function recordableOrder(Request $request): Order
    {
        $request->validate(['orderId' => ['required', 'integer']]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->isCompleted, 400, t('Transactions can only be recorded on completed orders.', category: 'commerce'));
        abort_unless($order->canView($this->user()), 403, 'User not authorized to view this order.');
        $this->abortIfEditing($order);

        return $order;
    }

    private function transactionFor(Request $request): Transaction
    {
        $request->validate(['id' => ['required', 'integer']]);

        $transaction = app(Transactions::class)->getTransactionById((int)$request->input('id'));
        abort_if(!$transaction, 404, t('Can not find the transaction.', category: 'commerce'));

        return $transaction;
    }

    private function abortIfEditing(Order $order): void
    {
        $reason = $this->guard->blockReason($order, $this->user()->id);
        abort_if($reason !== null, 423, (string)$reason);
    }

    private function user(): User
    {
        $user = currentUserElement();
        abort_if(!$user, 403);

        return $user;
    }
}
