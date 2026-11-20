<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Money;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\AddCustomLineItem;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use Illuminate\Support\Str;

use function CraftCms\Cms\t;

/**
 * Adds a line item that isn't backed by a purchasable.
 *
 * @since 6.0.0
 */
final readonly class AddCustomLineItemForm extends OrderOperationForm
{
    private const array FLAGS = ['isTaxable', 'isShippable', 'isPromotable', 'hasFreeShipping'];

    public static function type(): string
    {
        return AddCustomLineItem::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Add Custom Item', category: 'commerce');
    }

    public function submitLabel(Order $order): string
    {
        return t('Add', category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        $currency = $order->getStore()->getCurrency()->getCode();

        return Ui::make([
            Field::make(t('Description', category: 'commerce'), Text::make('description')->autofocus())->required(),
            Field::make(t('SKU', category: 'commerce'), Text::make('sku')->monospace())->required(),
            Field::make(t('Quantity', category: 'commerce'), Number::make('qty')->size(6))->required()->width(FieldWidth::Half),
            Field::make(t('Price', category: 'commerce'), Money::make('price')->currency($currency)->size(12))->required()->width(FieldWidth::Half),
            Field::make(t('Promotional Price', category: 'commerce'), Money::make('promotionalPrice')->currency($currency)->size(12))->width(FieldWidth::Half),
            Field::make(t('Tax Category', category: 'commerce'), Choice::make('taxCategoryId')->options(
                $this->choiceOptions(app(TaxCategories::class)->getAllTaxCategoriesAsList()),
            ))->width(FieldWidth::Half),
            Field::make(t('Shipping Category', category: 'commerce'), Choice::make('shippingCategoryId')->options(
                $this->choiceOptions(app(ShippingCategories::class)->getAllShippingCategoriesAsList($order->storeId)),
            ))->width(FieldWidth::Half),
            Field::make(t('Taxable', category: 'commerce'), Lightswitch::make('isTaxable'))->width(FieldWidth::Quarter),
            Field::make(t('Shippable', category: 'commerce'), Lightswitch::make('isShippable'))->width(FieldWidth::Quarter),
            Field::make(t('Promotable', category: 'commerce'), Lightswitch::make('isPromotable'))->width(FieldWidth::Quarter),
            Field::make(t('Free Shipping', category: 'commerce'), Lightswitch::make('hasFreeShipping'))->width(FieldWidth::Quarter),
            Field::make(t('Note', category: 'commerce'), Textarea::make('note')->rows(2)),
        ]);
    }

    public function values(Order $order, array $params): array
    {
        return [
            'description' => '',
            'sku' => '',
            'qty' => 1,
            'price' => null,
            'promotionalPrice' => null,
            'taxCategoryId' => (string)app(TaxCategories::class)->getDefaultTaxCategory()->id,
            'shippingCategoryId' => (string)app(ShippingCategories::class)->getDefaultShippingCategory($order->storeId)->id,
            'isTaxable' => true,
            'isShippable' => true,
            'isPromotable' => false,
            'hasFreeShipping' => false,
            'note' => '',
        ];
    }

    public function operationData(Order $order, array $input): array
    {
        $data = [
            'uid' => (string)Str::uuid(),
            'description' => $input['description'] ?? null,
            'sku' => $input['sku'] ?? null,
            'qty' => $input['qty'] ?? null,
            'price' => $this->money($input['price'] ?? null),
            'promotionalPrice' => $this->money($input['promotionalPrice'] ?? null),
            'taxCategoryId' => $input['taxCategoryId'] ?? null,
            'shippingCategoryId' => $input['shippingCategoryId'] ?? null,
            'note' => $input['note'] ?? '',
        ];

        foreach (self::FLAGS as $flag) {
            $data[$flag] = (bool)($input[$flag] ?? false);
        }

        return $data;
    }
}
