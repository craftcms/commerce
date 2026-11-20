<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * @since 6.0.0
 */
final readonly class SetCouponCode extends OrderOperation
{
    public function __construct(
        public ?string $couponCode,
    ) {
    }

    public static function type(): string
    {
        return 'setCouponCode';
    }

    public function label(): string
    {
        return t('Change Coupon Code', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'couponCode' => ['present', 'nullable', 'string'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['couponCode'] ?: null);
    }

    public function toArray(): array
    {
        return ['couponCode' => $this->couponCode];
    }

    public function summary(Order $before): string
    {
        return $this->change($before->couponCode, $this->couponCode);
    }

    public function apply(Order $order): void
    {
        $order->couponCode = $this->couponCode;
    }

    public function absorb(OrderOperation $earlier): ?static
    {
        return $earlier instanceof self ? $this : null;
    }

    public function isUnchangedFor(Order $order): bool
    {
        return (string)$order->couponCode === (string)$this->couponCode;
    }
}
