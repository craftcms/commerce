<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetLineItemOptions extends OrderOperation
{
    /** @param array<array-key, mixed> $options */
    public function __construct(
        public string $lineItemUid,
        public array $options,
    ) {
    }

    public static function type(): string
    {
        return 'setLineItemOptions';
    }

    public function label(): string
    {
        return t('Edit Line Item Options', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
            'options' => ['present', 'array'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['lineItemUid'], $data['options']);
    }

    public function toArray(): array
    {
        return [
            'lineItemUid' => $this->lineItemUid,
            'options' => $this->options,
        ];
    }

    public function apply(Order $order): void
    {
        $this->lineItem($order, $this->lineItemUid)->setOptions($this->options);
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self && $earlier->lineItemUid === $this->lineItemUid ? $this : null;
    }
}
