<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * @since 6.0.0
 */
final readonly class RemoveAddress extends OrderOperation
{
    public function __construct(
        public OrderAddressType $addressType,
    ) {
    }

    public static function type(): string
    {
        return 'removeAddress';
    }

    public static function rules(): array
    {
        return [
            'addressType' => ['required', 'in:' . implode(',', OrderAddressType::values())],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(OrderAddressType::from($data['addressType']));
    }

    public function toArray(): array
    {
        return ['addressType' => $this->addressType->value];
    }

    public function apply(Order $order): void
    {
        $this->addressType->setAddress($order, null);
    }
}
