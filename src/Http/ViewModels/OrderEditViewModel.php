<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Http\ViewModels\ViewModel;
use CraftCms\Cms\Markdown\Markdown;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Markdown as MarkdownFacade;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Http\Controllers\OrderEditController;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Data\OrderNotice;
use CraftCms\Commerce\Order\Edit\Operations\SetOrderSite;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Purchasable\PurchasableTypes;
use DateTimeInterface;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The Inertia payload for the order edit screen (`commerce::orders/Edit`).
 *
 * Amounts, dates and statuses are formatted here, so the page only lays them out.
 */
class OrderEditViewModel extends ViewModel
{
    public function __construct(
        private readonly Order $order,
    ) {
    }

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
     * Whether the current user can change the order from this page.
     */
    public function canEdit(): bool
    {
        $user = currentUserElement();

        return $user !== null && $this->order->canSave($user);
    }

    /**
     * @return array{form: string, apply: string}
     */
    public function editUrls(): array
    {
        return [
            'form' => action([OrderEditController::class, 'showForm']),
            'apply' => action([OrderEditController::class, 'apply']),
        ];
    }

    /**
     * The status badges shown in the page toolbar.
     *
     * @return list<string>
     */
    public function statusHtml(): array
    {
        return array_values(array_filter([
            $this->order->getOrderStatusHtml(),
            $this->order->isCompleted ? $this->order->getPaidStatusHtml() : null,
        ]));
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
     *     adjustments: list<array{type: string, name: string, description: string|null, amount: string, included: bool}>,
     * }>
     */
    public function lineItems(): array
    {
        $fulfilled = $this->fulfilledQuantities();

        return array_values(array_map(fn(LineItem $lineItem) => [
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
        ], $this->order->getLineItems()));
    }

    /**
     * The order's own adjustments (not attached to a line item).
     *
     * @return list<array{type: string, name: string, description: string|null, amount: string, included: bool}>
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

    /**
     * @return array{chipHtml: string, email: string|null}|null
     */
    public function customer(): ?array
    {
        $customer = $this->order->getCustomer();

        if (!$customer) {
            return null;
        }

        return [
            'chipHtml' => app(ElementHtml::class)->elementChipHtml($customer, ['hyperlink' => true]),
            'email' => $customer->email,
        ];
    }

    public function email(): ?string
    {
        return $this->order->getEmail();
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
     * @return list<array{label: string, value: string, form: string|null}>
     */
    public function details(): array
    {
        $details = [
            ['label' => t('Store', category: 'commerce'), 'value' => Html::encode($this->order->getStore()->getName()), 'form' => null],
            [
                'label' => t('Order Site', category: 'commerce'),
                'value' => Html::encode($this->order->getOrderSite()?->getName() ?? ''),
                'form' => Sites::isMultiSite() ? SetOrderSite::type() : null,
            ],
        ];

        if ($this->order->isCompleted) {
            $details[] = ['label' => t('Reference', category: 'commerce'), 'value' => Html::encode($this->order->reference ?? ''), 'form' => null];
            $details[] = ['label' => t('Date Ordered', category: 'commerce'), 'value' => $this->datetime($this->order->dateOrdered), 'form' => null];
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
     * @return list<array{newStatusHtml: string|null, prevStatusHtml: string|null, userHtml: string, date: string, messageHtml: string|null}>
     */
    public function histories(): array
    {
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
        ], $this->order->getHistories()));
    }

    /**
     * The purchasable types a product can be added from.
     *
     * @return list<array{type: string, label: string}>
     */
    public function purchasableTypes(): array
    {
        return app(PurchasableTypes::class)->types()
            ->map(fn(string $type) => ['type' => $type, 'label' => $type::displayName()])
            ->values()
            ->all();
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
            'remove' => t('Remove', category: 'commerce'),
            'confirmRemoveLineItem' => t('Remove {item} from the cart?', category: 'commerce'),
            'dismiss' => t('Dismiss', category: 'commerce'),
            'add' => t('Add', category: 'commerce'),
            'addLineItem' => t('Add a line item', category: 'commerce'),
            'addCustomLineItem' => t('Add a custom line item', category: 'commerce'),
            'addType' => t('Add {type}', category: 'commerce'),
            'chooseFromAddressBook' => t('Choose from Address Book', category: 'commerce'),
            'copyToShipping' => t('Copy to Shipping Address', category: 'commerce'),
            'copyToBilling' => t('Copy to Billing Address', category: 'commerce'),
            'removeAddress' => t('Remove Address', category: 'commerce'),
            'editFields' => t('Edit Fields', category: 'commerce'),
        ];
    }

    /**
     * @return array{type: string, name: string, description: string|null, amount: string, included: bool}
     */
    private function adjustmentData(OrderAdjustment $adjustment): array
    {
        return [
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
