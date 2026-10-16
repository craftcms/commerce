<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class RemoveLineItem extends OrderOperation
{
    public function __construct(
        public string $lineItemUid,
    ) {
    }

    public static function type(): string
    {
        return 'removeLineItem';
    }

    public function label(): string
    {
        return t('Remove Line Item', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'lineItemUid' => ['required', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['lineItemUid']);
    }

    public function toArray(): array
    {
        return ['lineItemUid' => $this->lineItemUid];
    }

    public function apply(Order $order): void
    {
        $order->removeLineItem($this->lineItem($order, $this->lineItemUid));
    }
}
