<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Money as MoneyControl;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Activity\OrderPaymentLinkChanged;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Payment\Exceptions\PaymentException;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\PaymentLinks;
use CraftCms\Commerce\Payment\Transactions;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Collecting a payment through a gateway with a hosted payment link for the customer, emailed or copied. The
 * gateway completes the payment through its webhook.
 *
 * Each action refuses while the order has an edit session with changes waiting. See {@see OrderEditGuard}.
 *
 * @since 6.0.0
 */
class OrderPaymentLinksController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly OrderEditGuard $guard,
        private readonly UiResolver $uiResolver,
        private readonly PaymentLinks $links,
    ) {
    }

    /**
     * The UI of the form that creates a payment link, for `openUiModal()`.
     */
    public function createForm(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $gateways = $this->linkGateways($order);

        abort_if($gateways === [], 422, t('No payment methods can create a payment link for this order.', category: 'commerce'));

        $ui = Ui::make([
            Field::make(
                t('Payment Method', category: 'commerce'),
                Choice::make('gatewayId')->options(array_map(fn(Gateway $gateway) => ['label' => $gateway->name, 'value' => (string)$gateway->id], $gateways))->withoutPlaceholder(),
            )->required(),
            Field::make(t('Amount', category: 'commerce'), MoneyControl::make('amount')->currency($order->currency)->max($order->getOutstandingBalance())->size(12))
                ->instructions(t('{amount} is outstanding.', ['amount' => $order->outstandingBalanceAsCurrency], category: 'commerce'))
                ->required(),
        ]);

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($ui, new UiContext(values: [
                'gatewayId' => (string)$gateways[0]->id,
                'amount' => ['value' => $order->getOutstandingBalance(), 'locale' => I18N::getFormattingLocale()->id],
            ])),
            'title' => t('Create Payment Link', category: 'commerce'),
            'submitLabel' => t('Create', category: 'commerce'),
        ]);
    }

    /**
     * Creates a payment link and returns its URL.
     */
    public function create(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $data = $request->validate(['gatewayId' => ['required', 'integer'], 'amount' => ['required']]);

        $gateway = app(Gateways::class)->getGatewayById((int)$data['gatewayId']);
        abort_if(!$gateway || $gateway->isArchived, 404, t('Can not find gateway.', category: 'commerce'));

        $amount = $data['amount'];
        $amount = is_array($amount) ? $amount : ['value' => $amount, 'locale' => I18N::getFormattingLocale()->id];

        try {
            $transaction = $this->links->create(
                $order,
                $gateway,
                (float)Money::toDecimal(Money::toMoney([...$amount, 'currency' => $order->currency])),
            );
        } catch (PaymentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return $this->asJsonSuccess(t('Payment link created.', category: 'commerce'), [
            'transactionId' => $transaction->id,
            'url' => $this->links->url($transaction),
        ]);
    }

    /**
     * Emails the payment link to the order’s customer with the `commerce_payment_link` system message.
     */
    public function email(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $link = $this->openLink($request, $order);

        if (!$order->email) {
            return new JsonResponse(['message' => t('This order has no email address.', category: 'commerce')], 422);
        }

        $expiresAt = $this->links->link($link)['expiresAt'] ?? null;

        try {
            Mail::to($order->email)->send(new SystemMessageMailable(
                key: 'commerce_payment_link',
                variables: [
                    'link' => $this->links->url($link),
                    'amount' => $link->getPaymentAmountAsCurrency(),
                    'expiresAt' => $expiresAt ? new DateTimeImmutable($expiresAt) : null,
                    'order' => $order,
                ],
            ));
        } catch (Throwable $exception) {
            report($exception);

            return new JsonResponse(['message' => t('Could not send email', category: 'commerce')], 422);
        }

        Activities::record(new OrderPaymentLinkChanged($order, OrderPaymentLinkChanged::EMAILED, $link->getPaymentAmountAsCurrency(), $link->getGateway()?->name, $order->email));

        return $this->asJsonSuccess(t('Email sent.', category: 'commerce'));
    }

    /**
     * The URL of a payment link, to copy.
     */
    public function url(Request $request): JsonResponse
    {
        $order = $this->order($request);

        return new JsonResponse(['url' => $this->links->url($this->openLink($request, $order))]);
    }

    /**
     * @return list<Gateway>
     */
    private function linkGateways(Order $order): array
    {
        return app(Gateways::class)->getAllGateways()
            ->filter(fn(Gateway $gateway) => !$gateway->isArchived
                && $gateway->supportsCollectPaymentLink()
                && $gateway->availableForUseWithOrder($order) !== false)
            ->values()
            ->all();
    }

    private function openLink(Request $request, Order $order): \CraftCms\Commerce\Payment\Data\Transaction
    {
        $request->validate(['id' => ['required', 'integer']]);

        $link = app(Transactions::class)->getTransactionById((int)$request->input('id'));
        abort_if(!$link || $link->orderId !== $order->id || !$this->links->isLink($link), 404, t('Can not find the transaction.', category: 'commerce'));
        abort_if(!in_array($link->id, array_map(fn($open) => $open->id, $this->links->openLinks($order)), true), 410, t('The payment link is no longer open.', category: 'commerce'));

        return $link;
    }

    private function order(Request $request): Order
    {
        $request->validate(['orderId' => ['required', 'integer']]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->isCompleted, 400, t('Payment links can only be created for completed orders.', category: 'commerce'));

        $user = $this->user();
        abort_unless($order->canView($user), 403, 'User not authorized to view this order.');

        $reason = $this->guard->blockReason($order, $user->id);
        abort_if($reason !== null, 423, (string)$reason);

        return $order;
    }

    private function user(): User
    {
        $user = currentUserElement();
        abort_if(!$user, 403);

        return $user;
    }
}
