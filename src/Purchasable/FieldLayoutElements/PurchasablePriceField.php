<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Money;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricing as CatalogPricingData;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasablePriceField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public ?string $label = '__blank__';

    #[Override]
    public string $attribute = 'price';

    #[Override]
    public bool $required = true;

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Price', category: 'commerce');
    }

    #[Override]
    public function uiNode(FieldLayoutElementContext $context): ?Node
    {
        $element = $context->element;
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        if (!$this->uid) {
            throw new InvalidArgumentException('Persisted Purchasable Price FieldLayout elements require stable UIDs.');
        }

        $static = $context->mode !== ControlMode::Editable || $context->ui->mode !== ControlMode::Editable;

        $currency = $element->getStore()->getCurrency()->getCode();
        $fields = [
            Field::make(t('Price', category: 'commerce'))
                ->required(!$static)
                ->width(FieldWidth::Half)
                ->control(
                    Money::make('basePrice')
                        ->currency($currency)
                        ->size(12)
                        ->value($element->basePrice ?? 0)
                        ->mode($context->mode)
                        ->reactive(),
                ),
        ];

        if (app(CatalogPricingRules::class)->canUseCatalogPricingRules()) {
            $fields[] = Field::make(t('Promotional Price', category: 'commerce'))
                ->width(FieldWidth::Half)
                ->control(
                    Money::make('basePromotionalPrice')
                        ->currency($currency)
                        ->size(12)
                        ->value($element->basePromotionalPrice)
                        ->mode($context->mode)
                        ->reactive(),
                );
        }

        return Group::make($this->uid, [...$fields, ...$this->priceNodes($element, $static)])
            ->asField()
            ->label($this->showLabel() ? $this->label() : null)
            ->instructions($this->instructionsText($element, $static))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element, $static))
            ->warning($this->warningText($element, $static))
            ->required(!$static && $this->required)
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    /**
     * The catalog prices (or, while sales are still in use, the related sales) for an existing purchasable.
     *
     * @return list<Node>
     */
    private function priceNodes(Purchasable $element, bool $static): array
    {
        if (!$element->id || $element->getIsDraft()) {
            return [];
        }

        if (app(CatalogPricingRules::class)->canUseCatalogPricingRules()) {
            if (app(CatalogPricingRules::class)->getAllCatalogPricingRulesByPurchasableId($element->id, $element->storeId)->isEmpty()) {
                return [];
            }

            $table = Table::make($this->uid . '-catalog-prices')
                ->columns([
                    ['key' => 'rule', 'label' => t('Catalog Pricing Rule', category: 'commerce')],
                    ['key' => 'price', 'label' => t('Price', category: 'commerce')],
                ])
                ->rows(app(CatalogPricing::class)->getCatalogPricesByPurchasableId($element->id, $element->storeId)
                    ->map(fn(CatalogPricingData $price) => [
                        'rule' => ($rule = $price->getCatalogPricingRule())
                            ? ['label' => $rule->name, 'url' => $rule->getCpEditUrl()]
                            : ($price->isPromotionalPrice ? t('Base Promotional Price', category: 'commerce') : t('Base Price', category: 'commerce')),
                        'price' => $price->getPriceAsCurrency(),
                    ])
                    ->values()
                    ->all());

            if (!$static) {
                $table->createAction(
                    t('Add catalog price', category: 'commerce'),
                    Url::urlWithParams($element->getStore()->getStoreSettingsUrl('pricing-rules/new'), ['n' => $element->id]),
                );
            }

            return [$table];
        }

        $sales = app(Sales::class)->getSalesRelatedToPurchasable($element);

        if (empty($sales)) {
            return [];
        }

        return [
            Table::make($this->uid . '-sales')
                ->columns([['key' => 'sale', 'label' => t('Sales', category: 'commerce')]])
                ->rows(array_map(fn(Sale $sale) => ['sale' => ['label' => $sale->name, 'url' => $sale->getCpEditUrl()]], $sales)),
        ];
    }
}
