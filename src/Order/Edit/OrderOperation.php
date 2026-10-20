<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use JsonSerializable;

use function CraftCms\Cms\t;

/**
 * A single change to an order, as made by one focused action on the order edit screen.
 *
 * Carts apply and save each operation as it's made. Completed orders collect them in an edit
 * session, where they're replayed on a freshly loaded order to preview the result and again to
 * commit it.
 *
 * @since 6.0.0
 */
abstract readonly class OrderOperation implements JsonSerializable
{
    abstract public static function type(): string;

    /**
     * Validation rules for the operation's data, as submitted.
     *
     * @return array<string, mixed>
     */
    abstract public static function rules(): array;

    /** @param array<string, mixed> $data Data that has passed {@see rules()}. */
    abstract public static function fromArray(array $data): static;

    /** @return array<string, mixed> */
    abstract public function toArray(): array;

    /**
     * @throws OperationNotApplicableException if the order can no longer take the change.
     */
    abstract public function apply(Order $order): void;

    /**
     * What the operation does, for listing it in an edit.
     */
    abstract public function label(): string;

    /**
     * A compact line saying what the operation changes, for listing it in an edit under its label.
     *
     * Called with the order as it stands just before the operation applies, so earlier operations
     * in the same edit are already reflected in it.
     */
    public function summary(Order $before): ?string
    {
        return null;
    }

    /**
     * Combines the operation with an earlier one it makes redundant, so an edit that changes the same
     * thing repeatedly lists it once.
     *
     * @return static|null the operation to use in place of both, or `null` if they're independent.
     */
    public function absorb(OrderOperation $earlier): ?static
    {
        return null;
    }

    /**
     * Whether the operation would leave the order as it already is, so it needn't be kept.
     */
    public function isUnchangedFor(Order $order): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['type' => static::type(), ...$this->toArray()];
    }

    protected function lineItem(Order $order, string $uid): LineItem
    {
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->uid === $uid) {
                return $lineItem;
            }
        }

        throw new OperationNotApplicableException(t('The line item no longer exists.', category: 'commerce'));
    }

    protected function none(): string
    {
        return t('None', category: 'commerce');
    }

    protected function change(?string $from, ?string $to): string
    {
        return t('{from} → {to}', [
            'from' => $from === null || $from === '' ? $this->none() : $from,
            'to' => $to === null || $to === '' ? $this->none() : $to,
        ], category: 'commerce');
    }

    protected function currency(Order $order, ?float $amount): string
    {
        return $amount === null ? $this->none() : Currency::formatAsCurrency($amount, $order->getStore()->getCurrency());
    }

    /**
     * The description of the order's line item, or an empty string if it has gone.
     */
    protected function itemName(Order $order, string $uid): string
    {
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->uid === $uid) {
                return $lineItem->getDescription();
            }
        }

        return '';
    }

    protected function itemSummary(Order $order, string $uid, string $change): string
    {
        return t('{item}: {change}', ['item' => $this->itemName($order, $uid), 'change' => $change], category: 'commerce');
    }

    protected function addressText(?Address $address): ?string
    {
        if (!$address) {
            return null;
        }

        return implode(', ', array_filter([$address->addressLine1, $address->locality, $address->countryCode])) ?: null;
    }

    protected function requireCompletedOrder(Order $order): void
    {
        if (!$order->isCompleted) {
            throw new OperationNotApplicableException(t('This change can only be made to a completed order.', category: 'commerce'));
        }
    }
}
