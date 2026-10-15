<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Forms;

use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Money;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Edit\Operations\UpdateLineItem;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;

use function CraftCms\Cms\t;

/**
 * Edits a line item's quantity, and whichever of its price, categories, description, SKU and
 * flags the order lets it change.
 *
 * A cart's purchasable line items take their price and categories from the purchasable each
 * time the cart is recalculated, so only their quantity is offered.
 *
 * @since 6.0.0
 */
final readonly class UpdateLineItemForm extends OrderOperationForm
{
    private const array FLAGS = ['isTaxable', 'isShippable', 'isPromotable', 'hasFreeShipping'];

    public static function type(): string
    {
        return UpdateLineItem::type();
    }

    public function title(Order $order, array $params): string
    {
        return t('Edit {item}', ['item' => $this->lineItem($order, $params)->getDescription()], category: 'commerce');
    }

    public function ui(Order $order, array $params): Ui
    {
        $lineItem = $this->lineItem($order, $params);
        $currency = $order->getStore()->getCurrency()->getCode();
        $nodes = [];

        if ($this->isCustom($lineItem)) {
            $nodes[] = Field::make(t('Description', category: 'commerce'), Text::make(['changes', 'description'])->autofocus())->required();
            $nodes[] = Field::make(t('SKU', category: 'commerce'), Text::make(['changes', 'sku'])->monospace())->required();
        }

        $nodes[] = Field::make(t('Quantity', category: 'commerce'), Number::make(['changes', 'qty'])->size(6))
            ->required()
            ->width(FieldWidth::Half);

        if ($this->editsPricing($order, $lineItem)) {
            $nodes[] = Field::make(t('Price', category: 'commerce'), Money::make(['changes', 'price'])->currency($currency)->size(12))
                ->required()
                ->width(FieldWidth::Half);
            $nodes[] = Field::make(t('Promotional Price', category: 'commerce'), Money::make(['changes', 'promotionalPrice'])->currency($currency)->size(12))
                ->width(FieldWidth::Half);
            $nodes[] = Field::make(t('Tax Category', category: 'commerce'), Choice::make(['changes', 'taxCategoryId'])->options(
                $this->choiceOptions(app(TaxCategories::class)->getAllTaxCategoriesAsList()),
            ))->width(FieldWidth::Half);
            $nodes[] = Field::make(t('Shipping Category', category: 'commerce'), Choice::make(['changes', 'shippingCategoryId'])->options(
                $this->choiceOptions(app(ShippingCategories::class)->getAllShippingCategoriesAsList($order->storeId)),
            ))->width(FieldWidth::Half);
        }

        if ($this->isCustom($lineItem)) {
            $nodes[] = Field::make(t('Taxable', category: 'commerce'), Lightswitch::make(['changes', 'isTaxable']))->width(FieldWidth::Quarter);
            $nodes[] = Field::make(t('Shippable', category: 'commerce'), Lightswitch::make(['changes', 'isShippable']))->width(FieldWidth::Quarter);
            $nodes[] = Field::make(t('Promotable', category: 'commerce'), Lightswitch::make(['changes', 'isPromotable']))->width(FieldWidth::Quarter);
            $nodes[] = Field::make(t('Free Shipping', category: 'commerce'), Lightswitch::make(['changes', 'hasFreeShipping']))->width(FieldWidth::Quarter);
        }

        return Ui::make($nodes);
    }

    public function values(Order $order, array $params): array
    {
        $lineItem = $this->lineItem($order, $params);
        $changes = ['qty' => $lineItem->qty];

        if ($this->isCustom($lineItem)) {
            $changes += [
                'description' => $lineItem->getDescription(),
                'sku' => $lineItem->getSku(),
                'isTaxable' => $lineItem->getIsTaxable(),
                'isShippable' => $lineItem->getIsShippable(),
                'isPromotable' => $lineItem->getIsPromotable(),
                'hasFreeShipping' => $lineItem->getHasFreeShipping(),
            ];
        }

        if ($this->editsPricing($order, $lineItem)) {
            $changes += [
                'price' => $lineItem->getPrice(),
                'promotionalPrice' => $lineItem->getPromotionalPrice(),
                'taxCategoryId' => (string)$lineItem->taxCategoryId,
                'shippingCategoryId' => (string)$lineItem->shippingCategoryId,
            ];
        }

        return ['changes' => $changes];
    }

    public function operationData(Order $order, array $input): array
    {
        $lineItem = $this->lineItem($order, $input);
        $submitted = (array)($input['changes'] ?? []);
        $changes = ['qty' => $submitted['qty'] ?? null];

        if ($this->isCustom($lineItem)) {
            $changes['description'] = $submitted['description'] ?? null;
            $changes['sku'] = $submitted['sku'] ?? null;

            foreach (self::FLAGS as $flag) {
                $changes[$flag] = (bool)($submitted[$flag] ?? false);
            }
        }

        if ($this->editsPricing($order, $lineItem)) {
            $changes['price'] = $this->money($submitted['price'] ?? null);
            $changes['promotionalPrice'] = $this->money($submitted['promotionalPrice'] ?? null);
            $changes['taxCategoryId'] = $submitted['taxCategoryId'] ?? null;
            $changes['shippingCategoryId'] = $submitted['shippingCategoryId'] ?? null;
        }

        return [
            'lineItemUid' => $lineItem->uid,
            'changes' => $changes,
        ];
    }

    private function isCustom(LineItem $lineItem): bool
    {
        return $lineItem->type === LineItemType::Custom;
    }

    private function editsPricing(Order $order, LineItem $lineItem): bool
    {
        return $order->isCompleted || $this->isCustom($lineItem);
    }
}
