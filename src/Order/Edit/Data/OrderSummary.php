<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Data;

use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use DateTimeInterface;
use JsonSerializable;

/**
 * The totals, line items and adjustments of an order, as returned after an edit is applied or
 * previewed.
 *
 * @since 6.0.0
 */
final readonly class OrderSummary implements JsonSerializable
{
    /**
     * @param list<array{uid: string|null, description: string, sku: string, qty: int, price: float, salePrice: float, subtotal: float, total: float}> $lineItems
     * @param list<array{id: int|null, type: string, name: string, description: string|null, amount: float, included: bool, lineItemUid: string|null}> $adjustments
     */
    public function __construct(
        public ?string $dateUpdated,
        public array $lineItems,
        public array $adjustments,
        public float $itemSubtotal,
        public float $totalDiscount,
        public float $totalShippingCost,
        public float $totalTax,
        public float $totalTaxIncluded,
        public float $totalPrice,
        public float $totalPaid,
        public float $outstandingBalance,
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        return new self(
            dateUpdated: $order->dateUpdated?->format(DateTimeInterface::ATOM),
            lineItems: array_values(array_map(fn(LineItem $lineItem) => [
                'uid' => $lineItem->uid,
                'description' => $lineItem->getDescription(),
                'sku' => $lineItem->getSku(),
                'qty' => $lineItem->qty,
                'price' => $lineItem->getPrice(),
                'salePrice' => $lineItem->getSalePrice(),
                'subtotal' => $lineItem->getSubtotal(),
                'total' => $lineItem->getTotal(),
            ], $order->getLineItems())),
            adjustments: array_values(array_map(fn(OrderAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'type' => $adjustment->type,
                'name' => $adjustment->name,
                'description' => $adjustment->description,
                'amount' => $adjustment->amount,
                'included' => $adjustment->included,
                'lineItemUid' => $adjustment->getLineItem()?->uid,
            ], $order->getAdjustments() ?? [])),
            itemSubtotal: $order->getItemSubtotal(),
            totalDiscount: $order->getTotalDiscount(),
            totalShippingCost: $order->getTotalShippingCost(),
            totalTax: $order->getTotalTax(),
            totalTaxIncluded: $order->getTotalTaxIncluded(),
            totalPrice: $order->getTotalPrice(),
            totalPaid: $order->getTotalPaid(),
            outstandingBalance: $order->getOutstandingBalance(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
