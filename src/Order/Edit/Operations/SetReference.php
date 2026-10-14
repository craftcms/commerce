<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * @since 6.0.0
 */
final readonly class SetReference extends OrderOperation
{
    public function __construct(
        public ?string $reference,
    ) {
    }

    public static function type(): string
    {
        return 'setReference';
    }

    public static function rules(): array
    {
        return [
            'reference' => ['present', 'nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['reference'] ?: null);
    }

    public function toArray(): array
    {
        return ['reference' => $this->reference];
    }

    public function apply(Order $order): void
    {
        $order->reference = $this->reference;
    }
}
