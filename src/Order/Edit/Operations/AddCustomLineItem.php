<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\LineItems;

use function CraftCms\Cms\t;

/**
 * Adds a line item that isn't backed by a purchasable.
 *
 * @since 6.0.0
 */
final readonly class AddCustomLineItem extends OrderOperation
{
    public function __construct(
        public string $uid,
        public string $description,
        public string $sku,
        public float $price,
        public int $taxCategoryId,
        public int $shippingCategoryId,
        public int $qty = 1,
        public ?float $promotionalPrice = null,
        public bool $hasFreeShipping = false,
        public bool $isPromotable = false,
        public bool $isShippable = true,
        public bool $isTaxable = true,
        public string $note = '',
    ) {
    }

    public static function type(): string
    {
        return 'addCustomLineItem';
    }

    public function label(): string
    {
        return t('Add Custom Line Item', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'uid' => ['required', 'uuid'],
            'description' => ['required', 'string'],
            'sku' => ['required', 'string'],
            'price' => ['required', 'numeric'],
            'promotionalPrice' => ['nullable', 'numeric'],
            'taxCategoryId' => ['required', 'integer'],
            'shippingCategoryId' => ['required', 'integer'],
            'qty' => ['integer', 'min:1'],
            'hasFreeShipping' => ['boolean'],
            'isPromotable' => ['boolean'],
            'isShippable' => ['boolean'],
            'isTaxable' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            uid: $data['uid'],
            description: $data['description'],
            sku: $data['sku'],
            price: (float)$data['price'],
            taxCategoryId: (int)$data['taxCategoryId'],
            shippingCategoryId: (int)$data['shippingCategoryId'],
            qty: (int)($data['qty'] ?? 1),
            promotionalPrice: isset($data['promotionalPrice']) ? (float)$data['promotionalPrice'] : null,
            hasFreeShipping: (bool)($data['hasFreeShipping'] ?? false),
            isPromotable: (bool)($data['isPromotable'] ?? false),
            isShippable: (bool)($data['isShippable'] ?? true),
            isTaxable: (bool)($data['isTaxable'] ?? true),
            note: $data['note'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => $this->price,
            'promotionalPrice' => $this->promotionalPrice,
            'taxCategoryId' => $this->taxCategoryId,
            'shippingCategoryId' => $this->shippingCategoryId,
            'qty' => $this->qty,
            'hasFreeShipping' => $this->hasFreeShipping,
            'isPromotable' => $this->isPromotable,
            'isShippable' => $this->isShippable,
            'isTaxable' => $this->isTaxable,
            'note' => $this->note,
        ];
    }

    public function summary(Order $before): string
    {
        return t('{description} · {qty} × {price}', [
            'description' => $this->description,
            'qty' => $this->qty,
            'price' => $this->currency($before, $this->promotionalPrice ?? $this->price),
        ], category: 'commerce');
    }

    public function apply(Order $order): void
    {
        $lineItem = app(LineItems::class)->create($order, [
            'qty' => $this->qty,
            'note' => $this->note,
            'uid' => $this->uid,
        ], LineItemType::Custom);

        $lineItem->setDescription($this->description);
        $lineItem->setSku($this->sku);
        $lineItem->setPrice($this->price);
        $lineItem->setPromotionalPrice($this->promotionalPrice);
        $lineItem->taxCategoryId = $this->taxCategoryId;
        $lineItem->shippingCategoryId = $this->shippingCategoryId;
        $lineItem->setHasFreeShipping($this->hasFreeShipping);
        $lineItem->setIsPromotable($this->isPromotable);
        $lineItem->setIsShippable($this->isShippable);
        $lineItem->setIsTaxable($this->isTaxable);

        $order->addLineItem($lineItem);
    }
}
