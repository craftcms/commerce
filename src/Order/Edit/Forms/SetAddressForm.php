<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Address\Addresses;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Field\Exceptions\InvalidFieldException;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\Operations\SetAddress;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Arr;

use function CraftCms\Cms\t;

/**
 * Edits the order's shipping or billing address, or copies one of the customer's addresses onto
 * it.
 *
 * The `addressType` param is `shipping` or `billing`. With `mode` set to `book` the form lists
 * the customer's addresses, otherwise it edits the address' own fields.
 *
 * @since 6.0.0
 */
final readonly class SetAddressForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetAddress::type();
    }

    public function title(Order $order, array $params): string
    {
        return match ($this->addressType($params)) {
            OrderAddressType::Shipping => t('Shipping Address', category: 'commerce'),
            OrderAddressType::Billing => t('Billing Address', category: 'commerce'),
        };
    }

    public function ui(Order $order, array $params): Ui
    {
        if ($this->isBook($params)) {
            return Ui::make([
                Field::make(t('Address Book', category: 'commerce'), Choice::make('sourceAddressId')
                    ->presentation(ChoicePresentation::Radios)
                    ->options($this->choiceOptions($this->bookLabels($order))))->required(),
            ]);
        }

        return app(FieldLayoutCompiler::class)->ui(
            app(Addresses::class)->getFieldLayout(),
            $this->address($order, $params),
            new UiContext(),
        );
    }

    /**
     * The address' own fields as a screen, optionally as the given values would leave it so the
     * fields that depend on the country, or on a condition, follow what the user has chosen.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $values
     */
    public function screenUi(Order $order, array $params, ?array $values = null): UiPayload
    {
        return app(FieldLayoutCompiler::class)->compile(
            app(Addresses::class)->getFieldLayout(),
            $values === null ? $this->address($order, $params) : $this->addressFromValues($values),
            new UiContext(
                namespace: [],
                values: $values ?? $this->values($order, $params),
                mode: ControlMode::Editable,
                refreshable: true,
            ),
        );
    }

    public function values(Order $order, array $params): array
    {
        if ($this->isBook($params)) {
            return ['sourceAddressId' => ''];
        }

        $address = $this->address($order, $params);

        return [
            ...OrderAddressType::attributesOf($address),
            'address' => Arr::only(OrderAddressType::attributesOf($address), ['addressLine1', 'addressLine2', 'addressLine3', 'administrativeArea', 'locality', 'dependentLocality', 'postalCode', 'sortingCode']),
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        $addressType = $this->addressType($input)->value;

        if ($this->isBook($input)) {
            return ['addressType' => $addressType, 'sourceAddressId' => $input['sourceAddressId'] ?? null];
        }

        $current = $this->addressType($input)->getAddress($order);
        $attributes = [
            ...($current ? OrderAddressType::attributesOf($current) : []),
            ...$input,
            ...($input['address'] ?? []),
        ];

        return [
            'addressType' => $addressType,
            'address' => Arr::only($attributes, [...OrderAddressType::attributeNames(), 'fields']),
        ];
    }

    /** @param array<string, mixed> $values */
    private function addressFromValues(array $values): Address
    {
        $address = new Address(Arr::only([...$values, ...($values['address'] ?? [])], OrderAddressType::attributeNames()));

        foreach ($values['fields'] ?? [] as $handle => $value) {
            try {
                $address->setFieldValueFromRequest((string)$handle, $value);
            } catch (InvalidFieldException) {
            }
        }

        return $address;
    }

    /** @param array<string, mixed> $params */
    private function addressType(array $params): OrderAddressType
    {
        return OrderAddressType::tryFrom((string)($params['addressType'] ?? '')) ?? abort(404, 'Unknown address type.');
    }

    /** @param array<string, mixed> $params */
    private function isBook(array $params): bool
    {
        return ($params['mode'] ?? null) === 'book';
    }

    /** @param array<string, mixed> $params */
    private function address(Order $order, array $params): Address
    {
        return $this->addressType($params)->getAddress($order)
            ?? new Address();
    }

    /** @return array<int, string> */
    private function bookLabels(Order $order): array
    {
        $labels = [];

        foreach (Address::find()->ownerId($order->getCustomerId())->all() as $address) {
            $labels[$address->id] = implode(', ', array_filter([$address->title, $address->addressLine1, $address->locality, $address->postalCode]));
        }

        return $labels;
    }
}
