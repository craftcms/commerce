<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Operations;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderOperation;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * Sets the order's shipping or billing address, either as a copy of one of the customer's
 * addresses or from the given attributes.
 *
 * @since 6.0.0
 */
final readonly class SetAddress extends OrderOperation
{
    /** @param array<string, mixed>|null $address */
    public function __construct(
        public OrderAddressType $addressType,
        public ?int $sourceAddressId = null,
        public ?array $address = null,
    ) {
    }

    public static function type(): string
    {
        return 'setAddress';
    }

    public function label(): string
    {
        return t('Change Address', category: 'commerce');
    }

    public static function rules(): array
    {
        return [
            'addressType' => ['required', 'in:' . implode(',', OrderAddressType::values())],
            'sourceAddressId' => ['nullable', 'integer', 'required_without:address'],
            'address' => ['nullable', 'array', 'required_without:sourceAddressId'],
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            OrderAddressType::from($data['addressType']),
            isset($data['sourceAddressId']) ? (int)$data['sourceAddressId'] : null,
            $data['address'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'addressType' => $this->addressType->value,
            'sourceAddressId' => $this->sourceAddressId,
            'address' => $this->address,
        ];
    }

    public function apply(Order $order): void
    {
        if ($this->sourceAddressId === null) {
            $this->addressType->setAddress($order, $this->address ?? []);

            return;
        }

        /** @var Address|null $source */
        $source = Address::find()->id($this->sourceAddressId)->one();

        if (!$source || $order->getCustomerId() === null || $source->getOwnerId() !== $order->getCustomerId()) {
            throw new OperationNotApplicableException(t('The address isn’t one of the customer’s addresses.', category: 'commerce'));
        }

        $this->addressType->setAddress($order, OrderAddressType::attributesOf($source), $source->id);
    }
}
