<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Money;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\PurchasableCatalogPrices;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Ui\Nodes\CatalogPrices;
use Illuminate\Support\Facades\DB;
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
        $status = $this->showStatus() ? $this->statusClass($element, $static) : null;
        $statusLabel = $status !== null
            ? ($this->statusLabel($element, $static) ?? ucfirst($status))
            : null;

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

            if ($catalogPrices = $this->catalogPricesNode($element)) {
                $fields[] = $catalogPrices;
            }
        }

        return Group::make($this->uid, $fields)
            ->asField()
            ->label($this->showLabel() ? $this->label() : null)
            ->showStatus($this->showStatus())
            ->orientation($this->orientation($element, $static))
            ->translatable(Sites::isMultiSite() && $this->translatable($element, $static), $this->translationDescription($element, $static))
            ->instructions($this->instructionsText($element, $static))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element, $static))
            ->warning($this->warningText($element, $static))
            ->required(!$static && $this->required)
            ->status($status, $statusLabel)
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    private function catalogPricesNode(Purchasable $element): ?CatalogPrices
    {
        if ($element->id === null || $element->getIsUnpublishedDraft() || $element->getIsRevision()) {
            return null;
        }

        $purchasableId = $element->getCanonicalId();
        $store = $element->getStore();

        return CatalogPrices::make(
            $this->uid . '-catalog-prices',
            app(PurchasableCatalogPrices::class)->data($purchasableId, $store),
            ['purchasableId' => $purchasableId, 'storeId' => $store->id],
        )
            ->createUrl(PurchasableCatalogPrices::canCreateRules()
                ? Url::urlWithParams($store->getStoreSettingsUrl('pricing-rules/new'), ['purchasableId' => $purchasableId])
                : null)
            ->pricesChanged($this->pricesChanged($element, $purchasableId));
    }

    private function pricesChanged(Purchasable $element, int $purchasableId): bool
    {
        $saved = DB::table(Table::PURCHASABLES_STORES)
            ->where('purchasableId', $purchasableId)
            ->where('storeId', $element->getStore()->id)
            ->first(['basePrice', 'basePromotionalPrice']);

        if ($saved === null) {
            return false;
        }

        $normalize = fn(mixed $price): ?float => $price === null || $price === '' ? null : round((float)$price, 4);

        return $normalize($saved->basePrice) !== $normalize($element->basePrice)
            || $normalize($saved->basePromotionalPrice) !== $normalize($element->basePromotionalPrice);
    }
}
