<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use craft\commerce\models\Sale;
use craft\commerce\web\assets\purchasablepricefield\PurchasablePriceFieldAsset;
use craft\web\assets\htmx\HtmxAsset;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

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

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        // TODO: these still register legacy yii2 AssetBundles (`craft\web\assets\htmx\HtmxAsset`,
        // `craft\commerce\web\assets\purchasablepricefield\PurchasablePriceFieldAsset`) via the
        // yii2-adapter bridge, since Commerce's own webpack-built CP assets haven't been ported to
        // a native `HtmlStack`-based registration mechanism yet.
        \Craft::$app->getView()->registerAssetBundle(HtmxAsset::class);

        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $basePrice = $element->basePrice;
        if (empty($element->errors()->get('basePrice'))) {
            if ($basePrice === null) {
                $basePrice = 0;
            }

            $basePrice = I18N::getFormatter()->asDecimal($basePrice);
        }

        $basePromotionalPrice = $element->basePromotionalPrice;
        if (empty($element->errors()->get('basePromotionalPrice')) && $basePromotionalPrice !== null) {
            $basePromotionalPrice = I18N::getFormatter()->asDecimal($basePromotionalPrice);
        }

        $id = InputNamespace::namespaceId('commerce-purchasable-price-field');
        $priceNamespace = InputNamespace::namespaceInputName('basePrice');
        $promotionalPriceNamespace = InputNamespace::namespaceInputName('basePromotionalPrice');

        /** @var CatalogPricingCondition $catalogPricingCondition */
        $catalogPricingCondition = Conditions::createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => true,
        ]);

        $purchasableConditionRule = Conditions::createConditionRule([
            'class' => CatalogPricingPurchasableConditionRule::class,
            'elementIds' => [$element::class => [$element->id]],
        ]);
        $catalogPricingCondition->addConditionRule($purchasableConditionRule);
        $conditionBuilderConfig = Json::encode($catalogPricingCondition->getConfig());

        \Craft::$app->getView()->registerAssetBundle(PurchasablePriceFieldAsset::class);

        $js = <<<JS
(() => {
    new Craft.Commerce.PurchasablePriceField('$id', {
        siteId: $element->siteId,
        conditionBuilderConfig: $conditionBuilderConfig,
        fieldNames: {
            price: '$priceNamespace',
            promotionalPrice: '$promotionalPriceNamespace',
        }
    });
})();
JS;
        HtmlStack::js($js, Position::BodyEnd);

        $canUseCatalogPricingRules = app(CatalogPricingRules::class)->canUseCatalogPricingRules();
        $toggleTitle = t('Show related sales', category: 'commerce');
        $toggleAttributes = ['class' => 'js-purchasable-toggle-container', 'style' => ['position' => 'relative']];
        $toggleContent = null;

        if ($canUseCatalogPricingRules) {
            $toggleTitle = t('Show all prices', category: 'commerce');
            $toggleAttributes['data-init-prices'] = 'true';
            $toggleContent = PurchasableHelper::catalogPricingRulesTableByPurchasableId($element->id, $element->storeId) .
                Html::beginTag('div', ['class' => 'flex']) .
                    // New catalog price button
                    Html::button(t('Add catalog price', category: 'commerce'), [
                        'class' => 'btn icon add js-cpr-slideout',
                        'data-icon' => 'plus',
                        'data-store-id' => $element->storeId,
                        'data-store-handle' => $element->getStore()->handle,
                        'data-purchasable-id' => $element->id,
                    ]) .
                    template('commerce/prices/_status', [
                        'areCatalogPricingJobsRunning' => app(CatalogPricing::class)->areCatalogPricingJobsRunning(),
                    ], TemplateMode::Cp) .
                Html::endTag('div');
        } else {
            /** @var Sale[] $relatedSales */
            $relatedSales = app(Sales::class)->getSalesRelatedToPurchasable($element);

            if (!empty($relatedSales)) {
                $salesTags = [];
                foreach ($relatedSales as $sale) {
                    $salesTags[] = Html::a($sale->name, $sale->getCpEditUrl());
                }

                $toggleContent = Html::tag('div', implode(', ', $salesTags));
            }
        }

        $toggleContent = $static ? null : $toggleContent;

        $currency = $element->getStore()->getCurrency();

        return Html::beginTag('div', [
                'id' => 'commerce-purchasable-price-field',
                'class' => 'js-purchasable-price-field',
            ]) .
            Html::beginTag('div', ['class' => 'flex']) .
                FormFields::fieldHtml(Currency::moneyInputHtml($basePrice, [
                    'id' => 'base-price',
                    'name' => 'basePrice',
                    'currency' => $currency->getCode(),
                    'currencyLabel' => $currency->getCode(),
                    'required' => true,
                    'errors' => $element->errors()->get('basePrice'),
                    'disabled' => $static,
                    'size' => 12,
                ]), [
                    'id' => 'base-price',
                    'required' => true,
                    'label' => t('Price', category: 'commerce'),
                ]) .

                // Don't show base promotional price field if the system is still using sales
                ($canUseCatalogPricingRules ?
                    FormFields::fieldHtml(Currency::moneyInputHtml($basePromotionalPrice, [
                        'id' => 'base-promotional-price',
                        'name' => 'basePromotionalPrice',
                        'currency' => $currency->getCode(),
                        'currencyLabel' => $currency->getCode(),
                        'errors' => $element->errors()->get('basePromotionalPrice'),
                        'disabled' => $static,
                        'size' => 12,
                    ]), [
                        'id' => 'promotional-price',
                        'label' => t('Promotional Price', category: 'commerce'),
                    ]) : '') .

            Html::endTag('div') .

            // Hide the prices table if the element is a draft
            ($toggleContent ? Html::beginTag('div', ['class' => $element->getIsDraft() ? 'hidden' : '']) .
                Html::tag('div',
                    Html::tag('a', $toggleTitle, ['class' => 'fieldtoggle', 'data-target' => 'purchasable-toggle']) .
                    Html::beginTag('div', $toggleAttributes) .
                    Html::tag(
                        'div',
                        // Prices table
                        $toggleContent,
                        [
                            'id' => 'purchasable-toggle',
                            'class' => 'hidden',
                        ]
                    ) .
                    Html::tag('div', '', [
                        'class' => 'js-purchasable-toggle-loading hidden',
                        'style' => [
                            'position' => 'absolute',
                            'top' => 0,
                            'left' => 0,
                            'width' => '100%',
                            'height' => '100%',
                            'background-color' => 'rgba(255, 255, 255, 0.5)',
                        ],
                    ]) .
                    Html::tag('div', Html::tag('span', '', ['class' => 'spinner']), [
                        'class' => 'js-purchasable-toggle-loading flex hidden',
                        'style' => [
                            'position' => 'absolute',
                            'top' => 0,
                            'left' => 0,
                            'width' => '100%',
                            'height' => '100%',
                            'align-items' => 'center',
                            'justify-content' => 'center',
                        ],
                    ]) .
                    Html::endTag('div')
                ) .
            Html::endTag('div') : '') .
        Html::endTag('div');
    }
}
