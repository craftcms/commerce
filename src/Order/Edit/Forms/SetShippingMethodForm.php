<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\SetShippingMethod;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Data\ShippingMethodOption;

use function CraftCms\Cms\t;

/**
 * Chooses from the shipping methods available to the order: those matching it, and for a
 * completed order any enabled method.
 *
 * @since 6.0.0
 */
final readonly class SetShippingMethodForm extends OrderOperationForm
{
    public static function type(): string
    {
        return SetShippingMethod::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Shipping Method', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        $options = array_map(fn(ShippingMethodOption $option) => [
            'label' => $option->matchesOrder
                ? t('{name} ({price})', ['name' => $option->getName(), 'price' => $option->getPriceAsCurrency()], category: 'commerce')
                : $option->getName(),
            'value' => $option->getHandle(),
        ], array_values($order->getAvailableShippingMethodOptions()));

        return Ui::make([
            Field::make(t('Shipping Method', category: 'commerce'), Choice::make('shippingMethodHandle')
                ->presentation(ChoicePresentation::Radios)
                ->options([['label' => t('None', category: 'commerce'), 'value' => ''], ...$options])),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return ['shippingMethodHandle' => (string)$order->shippingMethodHandle];
    }

    public function operationData(Order $order, array $input): array
    {
        return ['shippingMethodHandle' => $this->nullableId($input['shippingMethodHandle'] ?? null)];
    }
}
