<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Activity\Data\ActivitySubject;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Http\ViewModels\ViewModel;
use CraftCms\Cms\Markdown\Markdown;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Markdown as MarkdownFacade;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Http\Controllers\OrderActionsController;
use CraftCms\Commerce\Http\Controllers\OrderCustomersController;
use CraftCms\Commerce\Http\Controllers\OrderEditController;
use CraftCms\Commerce\Http\Controllers\OrderLifecycleController;
use CraftCms\Commerce\Http\Controllers\OrderPurchasablesController;
use CraftCms\Commerce\Http\Controllers\OrdersController;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Data\OrderNotice;
use CraftCms\Commerce\Order\Edit\Data\OrderEditSession;
use CraftCms\Commerce\Order\Edit\Data\OrderSummary;
use CraftCms\Commerce\Order\Edit\Operations\SetOrderSite;
use CraftCms\Commerce\Order\Edit\Operations\SetReference;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\OrderFulfillment;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderSnapshots;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Shipping\Data\ShippingMethodOption;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The Inertia payload for the order edit screen (`commerce::orders/Edit`).
 *
 * Amounts, dates and statuses are formatted here, so the page only lays them out.
 */
class OrderEditViewModel extends ViewModel
{
    /**
     * The shipping method combobox's value for "None": an empty value would leave the combobox
     * without a selection, and handles can't start with a hyphen.
     */
    private const string NO_SHIPPING_METHOD = '-none';

    /** A status change is saved moments before its activity is recorded, so they shouldn't both show. */
    private const int ACTIVITY_OVERLAP_SECONDS = 60;

    /**
     * @param OrderEditSession|null $session The order's edit session, whose edit the order has been previewed with.
     * @param Order|null $original The order as it was saved, before the session's edit.
     */
    public function __construct(
        private readonly Order $order,
        private readonly ?OrderEditSession $session = null,
        private readonly ?Order $original = null,
        private readonly ?string $sessionError = null,
    ) {
        $this->originalSummary = $original ? OrderSummary::fromOrder($original) : null;
    }

    private readonly ?OrderSummary $originalSummary;

    public function orderId(): int
    {
        return $this->order->id;
    }

    public function title(): string
    {
        return $this->order->isCompleted
            ? t('Order {reference}', ['reference' => $this->order->reference ?: $this->order->getShortNumber()], category: 'commerce')
            : t('Cart {number}', ['number' => $this->order->getShortNumber()], category: 'commerce');
    }

    /**
     * @return list<mixed>
     */
    public function crumbs(): array
    {
        return $this->order->getCrumbs();
    }

    public function isCompleted(): bool
    {
        return $this->order->isCompleted;
    }

    public function dateUpdated(): ?string
    {
        return $this->order->dateUpdated?->format(DateTimeInterface::ATOM);
    }

    /**
     * Why actions that save straight away are unavailable, such as a session with changes waiting.
     */
    public function immediateActionsBlockedReason(): ?string
    {
        return app(OrderEditGuard::class)->blockReason($this->order, currentUserElement()?->id);
    }

    private function userCan(string $permission): bool
    {
        return currentUserElement()?->can($permission) ?? false;
    }

    /**
     * The order's PDFs, for downloading.
     *
     * @return list<array{name: string, url: string}>
     */
    public function pdfs(): array
    {
        if (!$this->order->isCompleted || !$this->order->id) {
            return [];
        }

        return app(Pdfs::class)->getAllEnabledPdfs($this->order->storeId)
            ->map(fn($pdf) => [
                'name' => (string)$pdf->name,
                'url' => Url::actionUrl('commerce/downloads/pdf', ['number' => $this->order->number, 'pdfHandle' => $pdf->handle]),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether the store has an email to send for the order.
     */
    public function canEmail(): bool
    {
        return $this->order->isCompleted
            && $this->userCan('commerce-manageOrders')
            && app(Emails::class)->getAllEnabledEmails($this->order->storeId)->isNotEmpty();
    }

    /**
     * Whether the order has committed stock the user can fulfil, or return from fulfilled.
     */
    public function canFulfil(): bool
    {
        return $this->order->isCompleted
            && $this->userCan('commerce-manageOrders')
            && app(OrderFulfillment::class)->levels($this->order)->isNotEmpty();
    }

    /**
     * The order's committed and fulfilled stock, by line item and location.
     *
     * @return list<array{item: string, location: string, committed: int, fulfilled: int}>
     */
    public function fulfilment(): array
    {
        return app(OrderFulfillment::class)->levels($this->order)
            ->map(fn($level) => [
                'item' => $level->getLineItem()->getDescription(),
                'location' => (string)$level->getInventoryLocation()->name,
                'committed' => $level->committedQuantity,
                'fulfilled' => $level->fulfilledQuantity,
            ])
            ->values()
            ->all();
    }

    /**
     * What has a snapshot, which can be viewed on demand.
     *
     * @return list<array{key: string, name: string, context: string|null, type: string}>
     */
    public function snapshots(): array
    {
        return app(OrderSnapshots::class)->list($this->order);
    }

    /**
     * The emails that can be sent for the order.
     *
     * @return list<array{id: int, name: string}>
     */
    public function emails(): array
    {
        if (!$this->canEmail()) {
            return [];
        }

        return app(Emails::class)->getAllEnabledEmails($this->order->storeId)
            ->map(fn($email) => ['id' => (int)$email->id, 'name' => (string)$email->name])
            ->values()
            ->all();
    }

    /**
     * The customer addresses can be copied to, and the saved addresses that can be copied. An address
     * the edit session has changed isn't saved yet, so it can't be.
     *
     * @return array{userId: int, shippingAddressId: int|null, billingAddressId: int|null}|null
     */
    public function copyToUser(): ?array
    {
        $customer = $this->order->getCustomer();

        if (!$customer?->getIsCredentialed() || !$this->userCan('editUsers')) {
            return null;
        }

        $changes = $this->changes();

        return [
            'userId' => $customer->id,
            'shippingAddressId' => in_array('shippingAddress', $changes, true) ? null : $this->order->shippingAddressId,
            'billingAddressId' => in_array('billingAddress', $changes, true) ? null : $this->order->billingAddressId,
        ];
    }

    /**
     * The name of the status a cart is given when it's completed.
     */
    public function completionStatusName(): ?string
    {
        return $this->order->isCompleted ? null : app(OrderStatuses::class)->getDefaultOrderStatusForOrder($this->order)?->getUiLabel();
    }

    /**
     * Whether the current user can change the order from this page.
     */
    public function canEdit(): bool
    {
        $user = currentUserElement();

        return $user !== null && $this->order->canSave($user);
    }

    /**
     * @return array<string, string>
     */
    public function editUrls(): array
    {
        return [
            'form' => action([OrderEditController::class, 'showForm']),
            'apply' => action([OrderEditController::class, 'apply']),
            'fields' => action([OrderEditController::class, 'fieldsScreen']),
            'statusForm' => action([OrderLifecycleController::class, 'statusForm']),
            'status' => action([OrderLifecycleController::class, 'changeStatus']),
            'complete' => action([OrderLifecycleController::class, 'complete']),
            'address' => action([OrderEditController::class, 'addressScreen']),
            'snapshot' => action([OrderEditController::class, 'snapshot']),
            'customerSearch' => action([OrderCustomersController::class, 'search']),
            'purchasablesSearch' => action([OrderPurchasablesController::class, 'search']),
            'customerCreate' => action([OrderCustomersController::class, 'create']),
            'fulfillmentForm' => action([OrderActionsController::class, 'fulfillmentForm']),
            'fulfill' => action([OrderActionsController::class, 'fulfill']),
            'emailForm' => action([OrderActionsController::class, 'emailForm']),
            'sendEmail' => action([OrderActionsController::class, 'sendEmail']),
            'shareCart' => action([OrderActionsController::class, 'shareCart']),
            'copyAddressToUser' => action([OrdersController::class, 'copyAddressToUser']),
            'start' => action([OrderEditController::class, 'start']),
            'takeOver' => action([OrderEditController::class, 'takeOver']),
            'add' => action([OrderEditController::class, 'addOperation']),
            'remove' => action([OrderEditController::class, 'removeOperation']),
            'commit' => action([OrderEditController::class, 'commit']),
            'discard' => action([OrderEditController::class, 'discard']),
        ];
    }

    /**
     * The parts of the order the session's edit changes: any of `customer`, `shippingAddress`,
     * `billingAddress`, `shippingMethod`, `couponCode`, `reference`, `orderSite`, `dateOrdered`.
     *
     * @return list<string>
     */
    public function changes(): array
    {
        if (!$this->original) {
            return [];
        }

        $before = $this->original;
        $after = $this->order;

        return array_keys(array_filter([
            'customer' => $before->getCustomerId() !== $after->getCustomerId(),
            'shippingAddress' => $this->addressHtml($before->getShippingAddress()) !== $this->addressHtml($after->getShippingAddress()),
            'billingAddress' => $this->addressHtml($before->getBillingAddress()) !== $this->addressHtml($after->getBillingAddress()),
            'shippingMethod' => $before->shippingMethodHandle !== $after->shippingMethodHandle,
            'couponCode' => $before->couponCode !== $after->couponCode,
            'reference' => $before->reference !== $after->reference,
            'orderSite' => $before->orderSiteId !== $after->orderSiteId,
            'dateOrdered' => $before->dateOrdered?->getTimestamp() !== $after->dateOrdered?->getTimestamp(),
        ]));
    }

    /**
     * The handles of the custom fields the session's edit changes.
     *
     * @return list<string>
     */
    public function changedFields(): array
    {
        if (!$this->original) {
            return [];
        }

        $before = $this->original->getSerializedFieldValues();
        $after = $this->order->getSerializedFieldValues();

        return array_values(array_filter(
            array_keys($after),
            fn(string $handle) => json_encode($before[$handle] ?? null) !== json_encode($after[$handle]),
        ));
    }

    /**
     * The order's edit session, if it has one.
     *
     * The changes and totals are only included for the user who owns the session.
     *
     * @return array{
     *     mine: bool,
     *     userName: string|null,
     *     since: string,
     *     stale: bool,
     *     error: string|null,
     *     operations: list<array{index: int, label: string, summary: string|null}>,
     *     originalTotal: string|null,
     *     newTotal: string|null,
     *     totals: list<array{label: string, amount: string, emphasis: bool, divider: bool}>,
     * }|null
     */
    public function session(): ?array
    {
        if (!$this->session) {
            return null;
        }

        $mine = $this->session->belongsTo(currentUserElement()?->id);
        $editor = $this->session->userId ? User::find()->id($this->session->userId)->status(null)->one() : null;
        return [
            'mine' => $mine,
            'userName' => $editor?->getFriendlyName(),
            'since' => $this->datetime($this->session->dateCreated),
            'stale' => $this->session->edit->baseDateUpdated?->getTimestamp() !== $this->order->dateUpdated?->getTimestamp(),
            'error' => $mine ? $this->sessionError : null,
            'operations' => $mine ? $this->sessionOperations() : [],
            'originalTotal' => $mine && $this->originalSummary ? $this->currency($this->originalSummary->totalPrice) : null,
            'newTotal' => $mine ? $this->order->totalPriceAsCurrency : null,
            'totals' => $mine ? $this->sessionTotals() : [],
        ];
    }

    /**
     * The figures an edit changes, for laying out in columns.
     *
     * @return list<array{label: string, amount: string, emphasis: bool, divider: bool}>
     */
    private function sessionTotals(): array
    {
        if (!$this->originalSummary) {
            return [];
        }

        $original = $this->originalSummary->totalPrice;
        $new = $this->order->getTotalPrice();
        $difference = round($new - $original, 4);
        $outstanding = $this->order->getOutstandingBalance();

        $rows = [
            ['label' => t('Original total', category: 'commerce'), 'amount' => $this->currency($original), 'emphasis' => false, 'divider' => false],
            ['label' => t('New total', category: 'commerce'), 'amount' => $this->currency($new), 'emphasis' => true, 'divider' => false],
        ];

        if ($difference != 0) {
            $rows[] = [
                'label' => t('Change', category: 'commerce'),
                'amount' => ($difference > 0 ? '+' : '−') . $this->currency(abs($difference)),
                'emphasis' => false,
                'divider' => true,
            ];
        }

        if ($this->order->getTotalPaid() > 0) {
            $rows[] = ['label' => t('Paid', category: 'commerce'), 'amount' => $this->currency($this->order->getTotalPaid()), 'emphasis' => false, 'divider' => $difference == 0];
        }

        if ($outstanding != 0) {
            $rows[] = [
                'label' => $outstanding > 0 ? t('Still owed', category: 'commerce') : t('To refund', category: 'commerce'),
                'amount' => $this->currency(abs($outstanding)),
                'emphasis' => true,
                'divider' => $difference == 0 && $this->order->getTotalPaid() <= 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{index: int, label: string, summary: string|null}>
     */
    private function sessionOperations(): array
    {
        $operations = $this->session->edit->operations;
        $base = $operations ? app(Orders::class)->getOrderById($this->order->id) : null;
        $summaries = $base ? app(OrderOperations::class)->summaries($base, $operations) : [];

        return array_map(
            fn(OrderOperation $operation, int $index) => [
                'index' => $index,
                'label' => $operation->label(),
                'summary' => $summaries[$index] ?? null,
            ],
            $operations,
            array_keys($operations),
        );
    }

    /**
     * The status badges shown in the page toolbar.
     *
     * @return list<string>
     */
    public function statusHtml(): array
    {
        return $this->order->isCompleted ? [$this->order->getPaidStatusHtml()] : [];
    }

    /**
     * The order's status as a badge, for the details sidebar. Carts have none.
     */
    public function orderStatusHtml(): ?string
    {
        return $this->order->isCompleted ? ($this->order->getOrderStatusHtml() ?: null) : null;
    }

    /**
     * @return list<mixed>
     */
    public function actionMenu(): array
    {
        return $this->order->actionMenuDescriptors(ElementActionContext::Editor);
    }

    /**
     * @return list<array{id: int|null, noticeType: string, type: string, attribute: string, message: string, admin: bool}>
     */
    public function notices(): array
    {
        return array_map(fn(OrderNotice $notice) => [
            'id' => $notice->id,
            'noticeType' => $notice->getNoticeType()->value,
            'type' => $notice->type,
            'attribute' => $notice->attribute,
            'message' => $notice->message,
            'admin' => $notice->getNoticeType() === OrderNoticeType::Admin,
        ], [...$this->order->getAdminNotices(), ...$this->order->getNotices()]);
    }

    /**
     * @return list<array{
     *     uid: string|null,
     *     description: string,
     *     sku: string,
     *     url: string|null,
     *     qty: int,
     *     fulfilledQty: int,
     *     price: string,
     *     salePrice: string|null,
     *     subtotal: string,
     *     total: string,
     *     statusHtml: string|null,
     *     note: string,
     *     privateNote: string,
     *     options: list<array{key: string, value: string}>,
     *     adjustments: list<array{id: int|null, uid: string|null, type: string, name: string, description: string|null, amount: string, included: bool}>,
     *     change: string|null,
     * }>
     */
    public function lineItems(): array
    {
        $fulfilled = $this->fulfilledQuantities();

        $rows = array_values(array_map(fn(LineItem $lineItem) => [
            'uid' => $lineItem->uid,
            'description' => $lineItem->getDescription(),
            'sku' => $lineItem->getSku(),
            'url' => $lineItem->type === LineItemType::Purchasable ? $lineItem->getPurchasable()?->getCpEditUrl() : null,
            'qty' => $lineItem->qty,
            'fulfilledQty' => $fulfilled[$lineItem->id] ?? 0,
            'price' => $lineItem->getPriceAsCurrency(),
            'salePrice' => $lineItem->getSalePrice() !== $lineItem->getPrice() ? $lineItem->getSalePriceAsCurrency() : null,
            'subtotal' => $lineItem->getSubtotalAsCurrency(),
            'total' => $lineItem->getTotalAsCurrency(),
            'statusHtml' => $lineItem->getLineItemStatus()?->getLabelHtml(),
            'note' => $lineItem->note,
            'privateNote' => $lineItem->privateNote,
            'options' => array_map(
                fn(string|int $key, mixed $value) => ['key' => (string)$key, 'value' => is_scalar($value) ? (string)$value : (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                array_keys($lineItem->getOptions()),
                $lineItem->getOptions(),
            ),
            'adjustments' => array_values(array_map($this->adjustmentData(...), $lineItem->getAdjustments())),
            'change' => $this->lineItemChange($lineItem),
        ], $this->order->getLineItems()));

        return [...$rows, ...$this->removedLineItems()];
    }

    private function lineItemChange(LineItem $lineItem): ?string
    {
        if (!$this->originalSummary) {
            return null;
        }

        foreach ($this->originalSummary->lineItems as $before) {
            if ($before['uid'] !== $lineItem->uid) {
                continue;
            }

            $changed = $before['qty'] !== $lineItem->qty
                || $before['price'] != $lineItem->getPrice()
                || $before['salePrice'] != $lineItem->getSalePrice()
                || $before['total'] != $lineItem->getTotal();

            return $changed ? 'changed' : null;
        }

        return 'added';
    }

    /**
     * The line items the session's edit removes, as they were.
     *
     * @return list<array<string, mixed>>
     */
    private function removedLineItems(): array
    {
        if (!$this->originalSummary) {
            return [];
        }

        $current = array_map(fn(LineItem $lineItem) => $lineItem->uid, $this->order->getLineItems());
        $rows = [];

        foreach ($this->originalSummary->lineItems as $before) {
            if (in_array($before['uid'], $current, true)) {
                continue;
            }

            $rows[] = [
                'uid' => $before['uid'],
                'description' => $before['description'],
                'sku' => $before['sku'],
                'url' => null,
                'qty' => $before['qty'],
                'fulfilledQty' => 0,
                'price' => $this->currency($before['price']),
                'salePrice' => null,
                'subtotal' => $this->currency($before['subtotal']),
                'total' => $this->currency($before['total']),
                'statusHtml' => null,
                'note' => '',
                'privateNote' => '',
                'options' => [],
                'adjustments' => [],
                'change' => 'removed',
            ];
        }

        return $rows;
    }

    /**
     * The order's own adjustments (not attached to a line item).
     *
     * @return list<array{id: int|null, uid: string|null, type: string, name: string, description: string|null, amount: string, included: bool}>
     */
    public function adjustments(): array
    {
        return array_values(array_map($this->adjustmentData(...), $this->order->getOrderAdjustments()));
    }

    /**
     * @return list<array{label: string, amount: string}>
     */
    public function totals(): array
    {
        $rows = [
            ['label' => t('Subtotal', category: 'commerce'), 'amount' => $this->order->getItemSubtotalAsCurrency()],
        ];

        foreach ([
            [t('Discounts', category: 'commerce'), $this->order->getTotalDiscount()],
            [t('Shipping', category: 'commerce'), $this->order->getTotalShippingCost()],
            [t('Tax', category: 'commerce'), $this->order->getTotalTax()],
            [t('Tax (inc)', category: 'commerce'), $this->order->getTotalTaxIncluded()],
        ] as [$label, $amount]) {
            if ($amount != 0) {
                $rows[] = ['label' => $label, 'amount' => $this->currency($amount)];
            }
        }

        return $rows;
    }

    public function totalPrice(): string
    {
        return $this->order->totalPriceAsCurrency;
    }

    /**
     * @return array{paid: string, outstanding: string}|null
     */
    public function payments(): ?array
    {
        if (!$this->order->isCompleted) {
            return null;
        }

        return [
            'paid' => $this->order->totalPaidAsCurrency,
            'outstanding' => $this->order->outstandingBalanceAsCurrency,
        ];
    }

    public function couponCode(): ?string
    {
        return $this->order->couponCode;
    }

    public function shippingMethodName(): ?string
    {
        return $this->order->shippingMethodName;
    }

    public function reference(): ?string
    {
        return $this->order->reference;
    }

    public function orderSiteId(): string
    {
        return (string)$this->order->orderSiteId;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function orderSiteOptions(): array
    {
        return $this->order->getStore()->getSites()
            ->map(fn(Site $site) => ['label' => $site->getUiLabel(), 'value' => (string)$site->id])
            ->values()
            ->all();
    }

    public function shippingMethodHandle(): string
    {
        return $this->order->shippingMethodHandle ?: self::NO_SHIPPING_METHOD;
    }

    /**
     * @return list<array{label: string, value: string, data?: array<string, mixed>}>
     */
    public function shippingMethodOptions(): array
    {
        $options = array_map(fn(ShippingMethodOption $option) => [
            'label' => $option->matchesOrder
                ? t('{name} ({price})', ['name' => $option->getName(), 'price' => $option->getPriceAsCurrency()], category: 'commerce')
                : $option->getName(),
            'value' => $option->getHandle(),
            'data' => ['name' => $option->getName(), 'indicator' => ['fill' => $option->color]],
        ], array_values($this->order->getAvailableShippingMethodOptions()));

        return [
            ['label' => t('None', category: 'commerce'), 'value' => self::NO_SHIPPING_METHOD, 'data' => ['indicator' => ['appearance' => 'outline']]],
            ...$options,
        ];
    }

    /**
     * @return array{chipHtml: string}|null
     */
    public function customer(): ?array
    {
        $customer = $this->order->getCustomer();

        if (!$customer) {
            return null;
        }

        return [
            'chipHtml' => app(ElementHtml::class)->elementChipHtml($customer, ['hyperlink' => true]),
        ];
    }

    public function shippingAddressHtml(): ?string
    {
        return $this->addressHtml($this->order->getShippingAddress());
    }

    public function billingAddressHtml(): ?string
    {
        return $this->addressHtml($this->order->getBillingAddress());
    }

    /**
     * @return list<array{key: string, label: string, value: string, form: string|null}>
     */
    public function details(): array
    {
        $details = [
            ['key' => 'store', 'label' => t('Store', category: 'commerce'), 'value' => Html::encode($this->order->getStore()->getName()), 'form' => null],
            [
                'key' => 'orderSite',
                'label' => t('Order Site', category: 'commerce'),
                'value' => Html::encode($this->order->getOrderSite()?->getName() ?? ''),
                'form' => Sites::isMultiSite() ? SetOrderSite::type() : null,
            ],
        ];

        $details[] = ['key' => 'reference', 'label' => t('Reference', category: 'commerce'), 'value' => Html::encode($this->order->reference ?? ''), 'form' => SetReference::type()];

        if ($this->order->isCompleted) {
            $details[] = ['key' => 'dateOrdered', 'label' => t('Date Ordered', category: 'commerce'), 'value' => $this->datetime($this->order->dateOrdered), 'form' => null];
        }

        return $details;
    }

    public function metadataHtml(): string
    {
        return app(ContentHtml::class)->metadataHtml(array_filter([
            t('ID') => (string)$this->order->id,
            t('Number', category: 'commerce') => Html::tag('code', Html::encode($this->order->number)),
            t('Short Number', category: 'commerce') => Html::encode($this->order->getShortNumber()),
            t('Origin', category: 'commerce') => Html::encode(t(Str::title((string)$this->order->origin), category: 'commerce')),
            t('Last IP', category: 'commerce') => $this->order->lastIp ? Html::encode($this->order->lastIp) : false,
            t('Date Authorized', category: 'commerce') => $this->order->dateAuthorized ? $this->datetime($this->order->dateAuthorized) : false,
            t('Date Paid', category: 'commerce') => $this->order->datePaid ? $this->datetime($this->order->datePaid) : false,
            t('Created at') => $this->datetime($this->order->dateCreated),
            t('Updated at') => $this->datetime($this->order->dateUpdated),
        ], fn(string|false $value) => $value !== false));
    }

    /**
     * The order field layout's tabs, read-only. `null` when the layout has no custom fields.
     */
    public function fieldsUi(): ?UiPayload
    {
        $fieldLayout = $this->order->getFieldLayout();

        if ($fieldLayout->getCustomFields() === []) {
            return null;
        }

        return app(FieldLayoutCompiler::class)->compile(
            $fieldLayout,
            $this->order,
            new UiContext(
                namespace: [],
                errors: [],
                mode: ControlMode::ReadOnly,
            ),
        );
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     level: int,
     *     type: string,
     *     status: string,
     *     amount: string,
     *     paymentAmount: string,
     *     gateway: string,
     *     date: string,
     *     reference: string|null,
     *     message: string|null,
     *     note: string,
     * }>
     */
    public function transactions(): array
    {
        $transactions = $this->order->getTransactions();
        $levels = [];

        foreach ($transactions as $transaction) {
            $levels[$transaction->id] = $transaction->parentId !== null ? ($levels[$transaction->parentId] ?? 0) + 1 : 0;
        }

        return array_values(array_map(fn(Transaction $transaction) => [
            'id' => $transaction->id,
            'level' => $levels[$transaction->id] ?? 0,
            'type' => t(Str::title((string)$transaction->type), category: 'commerce'),
            'status' => t(Str::title((string)$transaction->status), category: 'commerce'),
            'amount' => $transaction->getAmountAsCurrency(),
            'paymentAmount' => $transaction->getPaymentAmountAsCurrency(),
            'gateway' => $transaction->getGateway()->name ?? t('Missing Gateway', category: 'commerce'),
            'date' => $this->datetime($transaction->dateUpdated),
            'reference' => $transaction->reference,
            'message' => $transaction->message,
            'note' => $transaction->note,
        ], $transactions));
    }

    /**
     * Where the order's activity timeline loads from, which is every kind of change recorded for it.
     *
     * @return array{url: string, elementType: string, elementId: int, siteId: int|null}|null
     */
    public function activity(): ?array
    {
        if (!$this->order->isCompleted || !$this->order->id) {
            return null;
        }

        return [
            'url' => Url::actionUrl('elements/activity'),
            'elementType' => $this->order::class,
            'elementId' => $this->order->id,
            'siteId' => $this->order->siteId,
        ];
    }

    /**
     * The status changes made before the order's activity was recorded, which the timeline can't show.
     *
     * @return list<array{newStatusHtml: string|null, prevStatusHtml: string|null, userHtml: string, date: string, messageHtml: string|null}>
     */
    public function histories(): array
    {
        $recordedFrom = $this->activityStart()?->getTimestamp();

        return array_values(array_map(fn(OrderHistory $history) => [
            'newStatusHtml' => $history->getNewStatus()?->getLabelHtml(),
            'prevStatusHtml' => $history->getPrevStatus()?->getLabelHtml(),
            'userHtml' => ($user = $history->getUser())
                ? app(ElementHtml::class)->elementChipHtml($user, ['hyperlink' => true])
                : Html::encode($history->userName ?: t('System', category: 'commerce')),
            'date' => $this->datetime($history->dateCreated),
            'messageHtml' => $history->message
                ? MarkdownFacade::parse(Html::encode($history->message), Markdown::FLAVOR_PRE_ENCODED)
                : null,
        ], array_filter(
            $this->order->getHistories(),
            fn(OrderHistory $history) => $recordedFrom === null || $history->dateCreated->getTimestamp() < $recordedFrom - self::ACTIVITY_OVERLAP_SECONDS,
        )));
    }

    /**
     * When the order's first activity was recorded.
     */
    private function activityStart(): ?DateTimeImmutable
    {
        if (!$this->order->id) {
            return null;
        }

        $first = Activities::query()
            /** @phpstan-ignore-next-line method.notFound (subject() is an Eloquent local scope declared with #[Scope] on ActivityEvent) */
            ->subject(ActivitySubject::fromElement($this->order))
            ->reorder()
            ->min('occurredAt');

        return $first ? new DateTimeImmutable((string)$first, new DateTimeZone('UTC')) : null;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [
            'details' => t('Details', category: 'commerce'),
            'transactions' => t('Transactions', category: 'commerce'),
            'history' => t('History', category: 'commerce'),
            'earlierHistory' => t('Earlier status changes', category: 'commerce'),
            'products' => t('Line Items', category: 'commerce'),
            'noProducts' => t('No products have been added.', category: 'commerce'),
            'item' => t('Item', category: 'commerce'),
            'qty' => t('Qty', category: 'commerce'),
            'fulfilled' => t('{count} fulfilled', category: 'commerce'),
            'price' => t('Price', category: 'commerce'),
            'total' => t('Total', category: 'commerce'),
            'note' => t('Note', category: 'commerce'),
            'privateNote' => t('Private Note', category: 'commerce'),
            'adjustments' => t('Adjustments', category: 'commerce'),
            'payment' => t('Totals', category: 'commerce'),
            'paid' => t('Paid', category: 'commerce'),
            'outstanding' => t('Outstanding', category: 'commerce'),
            'couponCode' => t('Coupon Code', category: 'commerce'),
            'customer' => t('Customer', category: 'commerce'),
            'noCustomer' => t('No customer', category: 'commerce'),
            'shippingAddress' => t('Shipping Address', category: 'commerce'),
            'billingAddress' => t('Billing Address', category: 'commerce'),
            'noAddress' => t('No address', category: 'commerce'),
            'shippingMethod' => t('Shipping Method', category: 'commerce'),
            'none' => t('None', category: 'commerce'),
            'save' => t('Save'),
            'removeCouponHint' => t('Leave blank to remove the coupon.', category: 'commerce'),
            'notices' => t('Notices', category: 'commerce'),
            'type' => t('Type', category: 'commerce'),
            'status' => t('Status', category: 'commerce'),
            'amount' => t('Amount', category: 'commerce'),
            'paymentAmount' => t('Payment Amount', category: 'commerce'),
            'gateway' => t('Gateway', category: 'commerce'),
            'date' => t('Date', category: 'commerce'),
            'reference' => t('Reference', category: 'commerce'),
            'noTransactions' => t('No transactions yet.', category: 'commerce'),
            'previousStatus' => t('Previous Status', category: 'commerce'),
            'updatedBy' => t('Updated By', category: 'commerce'),
            'message' => t('Message', category: 'commerce'),
            'noHistory' => t('No status changes yet.', category: 'commerce'),
            'orderTabs' => t('Order', category: 'commerce'),
            'actions' => t('Actions', category: 'commerce'),
            'edit' => t('Edit', category: 'commerce'),
            'editItem' => t('Edit {item}', category: 'commerce'),
            'setStatus' => t('Set Status', category: 'commerce'),
            'notes' => t('Notes', category: 'commerce'),
            'options' => t('Options', category: 'commerce'),
            'viewPurchasable' => t('View Purchasable', category: 'commerce'),
            'addresses' => t('Addresses', category: 'commerce'),
            'searchCustomers' => t('Search or enter customer email…', category: 'commerce'),
            'removeCustomer' => t('Remove customer', category: 'commerce'),
            'changeCustomer' => t('Change Customer', category: 'commerce'),
            'customerSearching' => t('Searching…', category: 'commerce'),
            'noCustomersFound' => t('No customers found. Enter an email address to create one.', category: 'commerce'),
            'createCustomerFor' => t('Create customer: “{email}”', category: 'commerce'),
            'location' => t('Location', category: 'commerce'),
            'committed' => t('Committed', category: 'commerce'),
            'fulfilledHeading' => t('Fulfilled', category: 'commerce'),
            'name' => t('Name', category: 'commerce'),
            'emails' => t('Emails', category: 'commerce'),
            'email' => t('Email', category: 'commerce'),
            'pdfs' => t('PDFs', category: 'commerce'),
            'download' => t('Download', category: 'commerce'),
            'send' => t('Send', category: 'commerce'),
            'fulfilment' => t('Fulfilment', category: 'commerce'),
            'fulfilmentHint' => t('Record committed stock as fulfilled when items ship.', category: 'commerce'),
            'noFulfilment' => t('No stock is committed for this order. Stock is committed for line items with tracked inventory.', category: 'commerce'),
            'communication' => t('Communication', category: 'commerce'),
            'emailsHint' => t('Send one of the store’s emails for this order. Each email goes to its own recipients.', category: 'commerce'),
            'pdfsHint' => t('Download the order as a PDF.', category: 'commerce'),
            'shareCartHeading' => t('Share', category: 'commerce'),
            'internal' => t('Internal', category: 'commerce'),
            'notesHint' => t('Notes are saved on each line item. Customer notes are visible to the customer, and internal notes are only visible to staff.', category: 'commerce'),
            'addNote' => t('Add note', category: 'commerce'),
            'noNote' => t('No notes', category: 'commerce'),
            'snapshots' => t('Snapshots', category: 'commerce'),
            'snapshotsHint' => t('The data saved with each line item and adjustment when it was added to the order.', category: 'commerce'),
            'showData' => t('Show data', category: 'commerce'),
            'hideData' => t('Hide data', category: 'commerce'),
            'loadingData' => t('Loading…', category: 'commerce'),
            'remove' => t('Remove', category: 'commerce'),
            'confirmRemoveLineItem' => t('Remove {item} from the cart?', category: 'commerce'),
            'dismiss' => t('Dismiss', category: 'commerce'),
            'add' => t('Add', category: 'commerce'),
            'addLineItem' => t('Add a line item', category: 'commerce'),
            'addCustomLineItem' => t('Add a custom line item', category: 'commerce'),
            'description' => t('Description', category: 'commerce'),
            'sku' => t('SKU', category: 'commerce'),
            'stock' => t('Stock', category: 'commerce'),
            'available' => t('Available', category: 'commerce'),
            'unavailable' => t('Unavailable', category: 'commerce'),
            'quantity' => t('Quantity', category: 'commerce'),
            'search' => t('Search', category: 'commerce'),
            'allTypes' => t('All Types', category: 'commerce'),
            'selectedCount' => t('{count} selected', category: 'commerce'),
            'cancel' => t('Cancel', category: 'commerce'),
            'failed' => t('Couldn’t add the items.', category: 'commerce'),
            'loadFailed' => t('Couldn’t load the purchasables.', category: 'commerce'),
            'fixItems' => t('Fix the highlighted items and try again.', category: 'commerce'),
            'noPurchasables' => t('No purchasables found.', category: 'commerce'),
            'priceSortedByLive' => t('Sorts by the price this order would be charged.', category: 'commerce'),
            'priceSortedByBase' => t('Sorts by the base price.', category: 'commerce'),
            'chooseFromAddressBook' => t('Choose from Address Book', category: 'commerce'),
            'copyToShipping' => t('Copy to Shipping Address', category: 'commerce'),
            'copyToBilling' => t('Copy to Billing Address', category: 'commerce'),
            'removeAddress' => t('Remove Address', category: 'commerce'),
            'copyToCustomer' => t('Copy to Customer’s Address Book', category: 'commerce'),
            'addressCopied' => t('Address copied to user.', category: 'commerce'),
            'fulfil' => t('Fulfil', category: 'commerce'),
            'downloadPdf' => t('Download PDF', category: 'commerce'),
            'sendEmail' => t('Send Email', category: 'commerce'),
            'shareCart' => t('Share Cart', category: 'commerce'),
            'linkCopied' => t('Link copied.', category: 'commerce'),
            'copy' => t('Copy', category: 'commerce'),
            'shareCartHint' => t('Anyone with this link can load the cart.', category: 'commerce'),
            'addAdjustment' => t('Add Adjustment', category: 'commerce'),
            'editAdjustment' => t('Edit Adjustment', category: 'commerce'),
            'removeAdjustment' => t('Remove Adjustment', category: 'commerce'),
            'recalculate' => t('Recalculate Order', category: 'commerce'),
            'confirmRecalculate' => t('Recalculate the order? Prices are refreshed from the purchasables and manual adjustments are replaced.', category: 'commerce'),
            'editFields' => t('Edit Fields', category: 'commerce'),
            'fieldModified' => t('This field has been modified.'),
            'discardChanges' => t('Discard changes'),
            'editingNotice' => t('Showing your unsaved changes.'),
            'editingOrder' => t('Editing Order', category: 'commerce'),
            'oneChange' => t('1 change', category: 'commerce'),
            'changeCount' => t('{count} changes', category: 'commerce'),
            'discard' => t('Discard', category: 'commerce'),
            'confirmDiscard' => t('Discard all changes to this order?', category: 'commerce'),
            'reviewAndUpdate' => t('Review and Update', category: 'commerce'),
            'markAsCompleted' => t('Mark as Completed', category: 'commerce'),
            'confirmComplete' => t('Mark this cart as completed with the status “{status}”?', category: 'commerce'),
            'reviewChanges' => t('Review Changes', category: 'commerce'),
            'updateOrder' => t('Update Order', category: 'commerce'),
            'noChanges' => t('No changes yet.', category: 'commerce'),
            'beingEditedBy' => t('Being edited by {name} since {date}.', category: 'commerce'),
            'takeOver' => t('Take Over', category: 'commerce'),
            'staleEdit' => t('This order has changed since you started editing it. Review the changes and update the order again.', category: 'commerce'),
            'changeAdded' => t('Added', category: 'commerce'),
            'changeChanged' => t('Changed', category: 'commerce'),
            'changeRemoved' => t('Removed', category: 'commerce'),
        ];
    }

    /**
     * @return array{id: int|null, uid: string|null, type: string, name: string, description: string|null, amount: string, included: bool}
     */
    private function adjustmentData(OrderAdjustment $adjustment): array
    {
        return [
            'id' => $adjustment->id,
            'uid' => $adjustment->uid,
            'type' => t(Str::title($adjustment->type), category: 'commerce'),
            'name' => $adjustment->name,
            'description' => $adjustment->description,
            'amount' => $adjustment->getAmountAsCurrency(),
            'included' => $adjustment->included,
        ];
    }

    /**
     * Fulfilled quantities, keyed by line item ID.
     *
     * @return array<int, int>
     */
    private function fulfilledQuantities(): array
    {
        if (!$this->order->isCompleted) {
            return [];
        }

        return app(Inventory::class)->getInventoryFulfillmentLevels($this->order)
            ->groupBy('lineItemId')
            ->map(fn($levels) => (int)abs($levels->sum('fulfilledQuantity')))
            ->all();
    }

    private function addressHtml(?Address $address): ?string
    {
        return $address ? Addresses::formatAddress($address) : null;
    }

    private function currency(float $amount): string
    {
        return Currency::formatAsCurrency($amount, $this->order->getStore()->getCurrency());
    }

    private function datetime(?DateTimeInterface $date): string
    {
        return $date ? I18N::getFormatter()->asDatetime($date, 'short') : '';
    }
}
