<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\OrderStockReconciliation;

use function CraftCms\Cms\t;

/**
 * Changes a line item's quantity, price or categories, and for custom line items its
 * description, SKU and flags.
 *
 * Only the keys present in `changes` are applied, so a `null` promotional price clears it.
 *
 * @since 6.0.0
 */
final readonly class UpdateLineItem extends OrderOperation
{
    private const array PRICE_KEYS = ['price', 'promotionalPrice'];

    private const array CUSTOM_KEYS = ['description', 'sku', 'hasFreeShipping', 'isPromotable', 'isShippable', 'isTaxable'];

    /**
     * @param array{
     *     qty?: int,
     *     price?: float,
     *     promotionalPrice?: float|null,
     *     taxCategoryId?: int,
     *     shippingCategoryId?: int,
     *     description?: string,
     *     sku?: string,
     *     hasFreeShipping?: bool,
     *     isPromotable?: bool,
     *     isShippable?: bool,
     *     isTaxable?: bool,
     * } $changes
     */
    public function __construct(
        public string $lineItemUid,
        public array $changes,
    ) {
    }

    public static function type(): string
    {
        return 'updateLineItem';
    }

    public function label(): string
    {
        return t('Update Line Item', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
            'changes' => ['required', 'array'],
            'changes.qty' => ['integer', 'min:1'],
            'changes.price' => ['numeric'],
            'changes.promotionalPrice' => ['nullable', 'numeric'],
            'changes.taxCategoryId' => ['integer'],
            'changes.shippingCategoryId' => ['integer'],
            'changes.description' => ['string'],
            'changes.sku' => ['string'],
            'changes.hasFreeShipping' => ['boolean'],
            'changes.isPromotable' => ['boolean'],
            'changes.isShippable' => ['boolean'],
            'changes.isTaxable' => ['boolean'],
        ];
    }

    public static function fromArray(array $data): static
    {
        $changes = array_intersect_key($data['changes'], array_flip([
            'qty',
            'taxCategoryId',
            'shippingCategoryId',
            ...self::PRICE_KEYS,
            ...self::CUSTOM_KEYS,
        ]));

        foreach (['qty', 'taxCategoryId', 'shippingCategoryId'] as $key) {
            if (isset($changes[$key])) {
                $changes[$key] = (int)$changes[$key];
            }
        }

        foreach (self::PRICE_KEYS as $key) {
            if (isset($changes[$key])) {
                $changes[$key] = (float)$changes[$key];
            }
        }

        foreach (['hasFreeShipping', 'isPromotable', 'isShippable', 'isTaxable'] as $key) {
            if (isset($changes[$key])) {
                $changes[$key] = (bool)$changes[$key];
            }
        }

        return new self($data['lineItemUid'], $changes);
    }

    public function toArray(): array
    {
        return [
            'lineItemUid' => $this->lineItemUid,
            'changes' => $this->changes,
        ];
    }

    public function summary(Order $before): ?string
    {
        $lineItem = $this->lineItem($before, $this->lineItemUid);
        $parts = [];

        if (isset($this->changes['qty']) && $this->changes['qty'] !== $lineItem->qty) {
            $parts[] = $this->labelledChange(t('Quantity', category: 'commerce'), (string)$lineItem->qty, (string)$this->changes['qty']);
        }

        if (isset($this->changes['price']) && (float)$this->changes['price'] !== $lineItem->getPrice()) {
            $parts[] = $this->labelledChange(t('Price', category: 'commerce'), $this->currency($before, $lineItem->getPrice()), $this->currency($before, (float)$this->changes['price']));
        }

        if (array_key_exists('promotionalPrice', $this->changes) && $this->changes['promotionalPrice'] !== $lineItem->getPromotionalPrice()) {
            $next = $this->changes['promotionalPrice'] === null ? null : (float)$this->changes['promotionalPrice'];
            $parts[] = $this->labelledChange(t('Promotional Price', category: 'commerce'), $this->currency($before, $lineItem->getPromotionalPrice()), $this->currency($before, $next));
        }

        $others = [];

        foreach ([
            'taxCategoryId' => t('Tax Category', category: 'commerce'),
            'shippingCategoryId' => t('Shipping Category', category: 'commerce'),
            'description' => t('Description', category: 'commerce'),
            'sku' => t('SKU', category: 'commerce'),
        ] as $key => $label) {
            if (isset($this->changes[$key])) {
                $others[] = $label;
            }
        }

        if ($others) {
            $parts[] = implode(', ', $others);
        }

        return $parts ? $this->itemSummary($before, $this->lineItemUid, implode(' · ', $parts)) : null;
    }

    private function labelledChange(string $label, string $from, string $to): string
    {
        return t('{label}: {from} → {to}', ['label' => $label, 'from' => $from, 'to' => $to], category: 'commerce');
    }

    public function apply(Order $order): void
    {
        $lineItem = $this->lineItem($order, $this->lineItemUid);
        $isCustom = $lineItem->type === LineItemType::Custom;

        if (!$isCustom && !$order->isCompleted && array_intersect_key($this->changes, array_flip(self::PRICE_KEYS))) {
            throw new OperationNotApplicableException(t('Prices can only be overridden on completed orders or custom line items.', category: 'commerce'));
        }

        if (!$isCustom && array_intersect_key($this->changes, array_flip(self::CUSTOM_KEYS))) {
            throw new OperationNotApplicableException(t('Only custom line items have an editable description, SKU and flags.', category: 'commerce'));
        }

        $changes = $this->changes;

        if (isset($changes['qty'])) {
            $fulfilled = $order->isCompleted ? app(OrderStockReconciliation::class)->fulfilledQuantity($lineItem) : 0;

            if ($changes['qty'] < $fulfilled) {
                throw new OperationNotApplicableException(t('{item} has {fulfilled} fulfilled, so its quantity can’t go below that.', ['item' => $lineItem->getDescription(), 'fulfilled' => $fulfilled], category: 'commerce'));
            }

            $lineItem->qty = $changes['qty'];
        }

        if (isset($changes['price'])) {
            $lineItem->setPrice($changes['price']);
        }

        if (array_key_exists('promotionalPrice', $changes)) {
            $lineItem->setPromotionalPrice($changes['promotionalPrice']);
        }

        if (isset($changes['taxCategoryId'])) {
            $lineItem->taxCategoryId = $changes['taxCategoryId'];
        }

        if (isset($changes['shippingCategoryId'])) {
            $lineItem->shippingCategoryId = $changes['shippingCategoryId'];
        }

        if (isset($changes['description'])) {
            $lineItem->setDescription($changes['description']);
        }

        if (isset($changes['sku'])) {
            $lineItem->setSku($changes['sku']);
        }

        if (isset($changes['hasFreeShipping'])) {
            $lineItem->setHasFreeShipping($changes['hasFreeShipping']);
        }

        if (isset($changes['isPromotable'])) {
            $lineItem->setIsPromotable($changes['isPromotable']);
        }

        if (isset($changes['isShippable'])) {
            $lineItem->setIsShippable($changes['isShippable']);
        }

        if (isset($changes['isTaxable'])) {
            $lineItem->setIsTaxable($changes['isTaxable']);
        }
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        if (!$earlier instanceof self || $earlier->lineItemUid !== $this->lineItemUid) {
            return null;
        }

        return new self($this->lineItemUid, [...$earlier->changes, ...$this->changes]);
    }
}
