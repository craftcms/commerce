<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\OrderStockReconciliation;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;

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

    public static function attributes(): array
    {
        return [
            'changes.qty' => t('Quantity', category: 'commerce'),
            'changes.price' => t('Price', category: 'commerce'),
            'changes.promotionalPrice' => t('Promotional Price', category: 'commerce'),
            'changes.taxCategoryId' => t('Tax Category', category: 'commerce'),
            'changes.shippingCategoryId' => t('Shipping Category', category: 'commerce'),
            'changes.description' => t('Description', category: 'commerce'),
            'changes.sku' => t('SKU', category: 'commerce'),
            'changes.hasFreeShipping' => t('Free Shipping', category: 'commerce'),
            'changes.isPromotable' => t('Promotable', category: 'commerce'),
            'changes.isShippable' => t('Shippable', category: 'commerce'),
            'changes.isTaxable' => t('Taxable', category: 'commerce'),
        ];
    }

    public function summary(Order $before): ?string
    {
        $parts = array_map(
            fn(array $change) => $this->labelledChange($change['label'], $change['from'], $change['to']),
            $this->changedFields($before),
        );

        return $parts ? $this->itemSummary($before, $this->lineItemUid, implode(' · ', $parts)) : null;
    }

    public function isUnchangedFor(Order $order): bool
    {
        try {
            return $this->changedFields($order) === [];
        } catch (OperationNotApplicableException) {
            return false;
        }
    }

    /**
     * The fields these changes would alter on the line item as it is in `$order`.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    private function changedFields(Order $order): array
    {
        $lineItem = $this->lineItem($order, $this->lineItemUid);
        $changed = [];

        if (isset($this->changes['qty']) && $this->changes['qty'] !== $lineItem->qty) {
            $changed[] = $this->field(t('Quantity', category: 'commerce'), (string)$lineItem->qty, (string)$this->changes['qty']);
        }

        if (isset($this->changes['price']) && !$this->sameAmount($this->changes['price'], $lineItem->getPrice())) {
            $changed[] = $this->field(t('Price', category: 'commerce'), $this->currency($order, $lineItem->getPrice()), $this->currency($order, (float)$this->changes['price']));
        }

        if (array_key_exists('promotionalPrice', $this->changes) && !$this->sameAmount($this->changes['promotionalPrice'], $lineItem->getPromotionalPrice())) {
            $changed[] = $this->field(t('Promotional Price', category: 'commerce'), $this->currency($order, $lineItem->getPromotionalPrice()), $this->currency($order, $this->changes['promotionalPrice']));
        }

        if (isset($this->changes['taxCategoryId']) && $this->changes['taxCategoryId'] !== $lineItem->taxCategoryId) {
            $changed[] = $this->field(t('Tax Category', category: 'commerce'), $this->taxCategoryName($lineItem->taxCategoryId), $this->taxCategoryName($this->changes['taxCategoryId']));
        }

        if (isset($this->changes['shippingCategoryId']) && $this->changes['shippingCategoryId'] !== $lineItem->shippingCategoryId) {
            $changed[] = $this->field(t('Shipping Category', category: 'commerce'), $this->shippingCategoryName($order, $lineItem->shippingCategoryId), $this->shippingCategoryName($order, $this->changes['shippingCategoryId']));
        }

        if (isset($this->changes['description']) && $this->changes['description'] !== $lineItem->getDescription()) {
            $changed[] = $this->field(t('Description', category: 'commerce'), $lineItem->getDescription(), $this->changes['description']);
        }

        if (isset($this->changes['sku']) && $this->changes['sku'] !== $lineItem->getSku()) {
            $changed[] = $this->field(t('SKU', category: 'commerce'), $lineItem->getSku(), $this->changes['sku']);
        }

        foreach ([
            'isTaxable' => [t('Taxable', category: 'commerce'), $lineItem->getIsTaxable()],
            'isShippable' => [t('Shippable', category: 'commerce'), $lineItem->getIsShippable()],
            'isPromotable' => [t('Promotable', category: 'commerce'), $lineItem->getIsPromotable()],
            'hasFreeShipping' => [t('Free Shipping', category: 'commerce'), $lineItem->getHasFreeShipping()],
        ] as $key => [$label, $current]) {
            if (isset($this->changes[$key]) && $this->changes[$key] !== $current) {
                $changed[] = $this->field($label, $this->yesNo($current), $this->yesNo($this->changes[$key]));
            }
        }

        return $changed;
    }

    /**
     * @return array{label: string, from: string, to: string}
     */
    private function field(string $label, string $from, string $to): array
    {
        return ['label' => $label, 'from' => $from, 'to' => $to];
    }

    private function sameAmount(float|int|null $next, ?float $current): bool
    {
        if ($next === null || $current === null) {
            return $next === $current;
        }

        return abs((float)$next - $current) < 0.00001;
    }

    private function yesNo(bool $value): string
    {
        return $value ? t('Yes', category: 'commerce') : t('No', category: 'commerce');
    }

    private function taxCategoryName(?int $id): string
    {
        return ($id ? app(TaxCategories::class)->getTaxCategoryById($id)?->name : null) ?? $this->none();
    }

    private function shippingCategoryName(Order $order, ?int $id): string
    {
        return ($id ? app(ShippingCategories::class)->getShippingCategoryById($id, $order->storeId)?->name : null) ?? $this->none();
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
