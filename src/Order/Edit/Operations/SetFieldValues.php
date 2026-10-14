<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Sets custom field values on the order, keyed by field handle.
 *
 * @since 6.0.0
 */
final readonly class SetFieldValues extends OrderOperation
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public array $values,
    ) {
    }

    public static function type(): string
    {
        return 'setFieldValues';
    }

    public static function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['values']);
    }

    public function toArray(): array
    {
        return ['values' => $this->values];
    }

    public function apply(Order $order): void
    {
        $order->setFieldValues($this->values);
    }
}
