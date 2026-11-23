<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\ShippingMethods;

use function CraftCms\Cms\t;

/**
 * Sets or removes the order's shipping method.
 *
 * The name is needed for methods registered at runtime, which can't be looked up by handle.
 *
 * @since 6.0.0
 */
final readonly class SetShippingMethod extends OrderOperation
{
    public function __construct(
        public ?string $shippingMethodHandle,
        public ?string $shippingMethodName = null,
    ) {
    }

    public static function type(): string
    {
        return 'setShippingMethod';
    }

    public function label(): string
    {
        return t('Change Shipping Method', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'shippingMethodHandle' => ['present', 'nullable', 'string'],
            'shippingMethodName' => ['nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['shippingMethodHandle'], $data['shippingMethodName'] ?? null);
    }

    public function toArray(): array
    {
        return [
            'shippingMethodHandle' => $this->shippingMethodHandle,
            'shippingMethodName' => $this->shippingMethodName,
        ];
    }

    public function summary(Order $before): string
    {
        $next = $this->shippingMethodHandle
            ? ($this->shippingMethodName ?? app(ShippingMethods::class)->getShippingMethodByHandle($this->shippingMethodHandle)?->getName())
            : null;

        return $this->change($before->shippingMethodName, $next);
    }

    public function apply(Order $order): void
    {
        $order->shippingMethodHandle = $this->shippingMethodHandle;

        if (!$this->shippingMethodHandle) {
            $order->shippingMethodName = null;

            return;
        }

        $order->shippingMethodName = $this->shippingMethodName
            ?? app(ShippingMethods::class)->getShippingMethodByHandle($this->shippingMethodHandle)?->getName();
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self ? $this : null;
    }

    public function isUnchangedFor(Order $order): bool
    {
        return (string)$order->shippingMethodHandle === (string)$this->shippingMethodHandle;
    }
}
