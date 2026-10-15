<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\AddLineItem;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\PurchasableTypes;
use Illuminate\Support\Str;

use function CraftCms\Cms\t;

/**
 * Adds a purchasable to the order, chosen with the element selector.
 *
 * The `purchasableType` param names the purchasable element type to choose from, and defaults to
 * the first registered type.
 *
 * @since 6.0.0
 */
final readonly class AddLineItemForm extends OrderOperationForm
{
    public static function type(): string
    {
        return AddLineItem::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Add {type}', ['type' => $this->purchasableType($params)::lowerDisplayName()], category: 'commerce');
    }

    public function submitLabel(Order $order): string
    {
        return t('Add', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        return Ui::make([
            Field::make(
                $this->purchasableType($params)::displayName(),
                ElementSelect::make('purchasableId')
                    ->elementType($this->purchasableType($params))
                    ->criteria(['siteId' => $order->orderSiteId])
                    ->single(),
            )->required(),
            Field::make(t('Quantity', category: 'commerce'), Number::make('qty')->size(6))->required()->width(FieldWidth::Half),
            Field::make(t('Note', category: 'commerce'), Textarea::make('note')->rows(2)),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return [
            'purchasableId' => null,
            'qty' => 1,
            'note' => '',
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        return [
            'uid' => (string)Str::uuid(),
            'purchasableId' => $input['purchasableId'] ?? null,
            'qty' => $input['qty'] ?? null,
            'note' => $input['note'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return class-string<PurchasableInterface>
     */
    private function purchasableType(array $params): string
    {
        $types = app(PurchasableTypes::class)->types();
        $type = $params['purchasableType'] ?? null;

        return $types->contains($type) ? $type : $types->first();
    }
}
