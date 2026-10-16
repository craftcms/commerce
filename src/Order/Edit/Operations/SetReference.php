<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

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

    public function label(): string
    {
        return t('Change Reference', category: 'commerce');
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

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self ? $this : null;
    }

    public function isUnchangedFor(Order $order): bool
    {
        return (string)$order->reference === (string)$this->reference;
    }
}
