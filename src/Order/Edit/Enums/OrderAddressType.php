<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Enums;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Base\EnumHelpersTrait;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

enum OrderAddressType: string
{
    use EnumHelpersTrait;

    case Shipping = 'shipping';

    case Billing = 'billing';

    /**
     * The address attributes an order address takes from another address.
     */
    private const array ATTRIBUTES = [
        'title',
        'fullName',
        'firstName',
        'lastName',
        'organization',
        'organizationTaxId',
        'countryCode',
        'administrativeArea',
        'locality',
        'dependentLocality',
        'postalCode',
        'sortingCode',
        'addressLine1',
        'addressLine2',
        'addressLine3',
        'latitude',
        'longitude',
    ];

    /** @return list<string> */
    public static function attributeNames(): array
    {
        return self::ATTRIBUTES;
    }

    public function label(): string
    {
        return match ($this) {
            self::Shipping => t('Shipping Address', category: 'commerce'),
            self::Billing => t('Billing Address', category: 'commerce'),
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Shipping => self::Billing,
            self::Billing => self::Shipping,
        };
    }

    public function getAddress(Order $order): ?Address
    {
        return match ($this) {
            self::Shipping => $order->getShippingAddress(),
            self::Billing => $order->getBillingAddress(),
        };
    }

    public function getSourceAddressId(Order $order): ?int
    {
        return match ($this) {
            self::Shipping => $order->sourceShippingAddressId,
            self::Billing => $order->sourceBillingAddressId,
        };
    }

    /**
     * Sets the order's address from a set of attributes, in memory only: the address is saved
     * along with the order.
     *
     * @param array<string, mixed>|null $attributes
     */
    public function setAddress(Order $order, ?array $attributes, ?int $sourceAddressId = null): void
    {
        if ($attributes !== null) {
            $attributes = array_merge(array_fill_keys(self::ATTRIBUTES, null), $attributes);
        }

        match ($this) {
            self::Shipping => $order->setShippingAddress($attributes),
            self::Billing => $order->setBillingAddress($attributes),
        };

        match ($this) {
            self::Shipping => $order->sourceShippingAddressId = $sourceAddressId,
            self::Billing => $order->sourceBillingAddressId = $sourceAddressId,
        };
    }

    /** @return array<string, mixed> */
    public static function attributesOf(Address $address): array
    {
        $attributes = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $attributes[$attribute] = $address->$attribute;
        }

        $attributes['fields'] = $address->getSerializedFieldValues();

        return $attributes;
    }
}
