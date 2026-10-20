<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Copies the order's shipping address to its billing address, or the other way around.
 *
 * @since 6.0.0
 */
final readonly class CopyAddress extends OrderOperation
{
    public function __construct(
        public OrderAddressType $from,
    ) {
    }

    public static function type(): string
    {
        return 'copyAddress';
    }

    public function label(): string
    {
        return t('Copy {from} to {to}', ['from' => $this->from->label(), 'to' => $this->from->other()->label()], category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'from' => ['required', 'in:' . implode(',', OrderAddressType::values())],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(OrderAddressType::from($data['from']));
    }

    public function toArray(): array
    {
        return ['from' => $this->from->value];
    }

    public function summary(Order $before): ?string
    {
        return $this->addressText($this->from->getAddress($before));
    }


    public function apply(Order $order): void
    {
        $address = $this->from->getAddress($order);

        if (!$address) {
            throw new OperationNotApplicableException(t('There is no address to copy.', category: 'commerce'));
        }

        $this->from->other()->setAddress(
            $order,
            OrderAddressType::attributesOf($address),
            $this->from->getSourceAddressId($order),
        );
    }
}
