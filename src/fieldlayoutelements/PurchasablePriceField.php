<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\conditions\purchasables\CatalogPricingCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingPurchasableConditionRule;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\web\assets\purchasablepricefield\PurchasablePriceFieldAsset;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\assets\htmx\HtmxAsset;
use yii\base\InvalidArgumentException;

/**
 * PurchasablePriceField represents a Price field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasablePriceField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = '__blank__';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'price';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(HtmxAsset::class);

        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $basePrice = $element->basePrice;
        if (empty($element->getErrors('basePrice'))) {
            if ($basePrice === null) {
                $basePrice = 0;
            }

            $basePrice = Craft::$app->getFormatter()->asDecimal($basePrice);
        }

        $basePromotionalPrice = $element->basePromotionalPrice;
        if (empty($element->getErrors('basePromotionalPrice')) && $basePromotionalPrice !== null) {
            $basePromotionalPrice = Craft::$app->getFormatter()->asDecimal($basePromotionalPrice);
        }

        $id = $view->namespaceInputId('commerce-purchasable-price-field');
        $priceNamespace = $view->namespaceInputName('basePrice');
        $promotionalPriceNamespace = $view->namespaceInputName('basePromotionalPrice');

        /** @var CatalogPricingCondition $catalogPricingCondition */
        $catalogPricingCondition = Craft::$app->getConditions()->createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => true,
        ]);

        $purchasableConditionRule = Craft::$app->getConditions()->createConditionRule([
            'class' => CatalogPricingPurchasableConditionRule::class,
            'elementIds' => [get_class($element) => [$element->id]],
        ]);
        $catalogPricingCondition->addConditionRule($purchasableConditionRule);
        $conditionBuilderConfig = Json::encode($catalogPricingCondition->getConfig());

        $view->registerAssetBundle(PurchasablePriceFieldAsset::class);

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
        $view->registerJs($js, $view::POS_END);

        $canUseCatalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules();
        $toggleTitle = Craft::t('commerce', 'Show related sales');
        $toggleAttributes = ['class' => 'js-purchasable-toggle-container', 'style' => ['position' => 'relative']];
        $toggleContent = null;

        if ($canUseCatalogPricingRules) {
            $toggleTitle = Craft::t('commerce', 'Show all prices');
            $toggleAttributes['data-init-prices'] = 'true';
            $toggleContent = PurchasableHelper::catalogPricingRulesTableByPurchasableId($element->id, $element->storeId) .
                Html::beginTag('div', ['class' => 'flex']) .
                // New catalog price button
                Html::button(Craft::t('commerce', 'Add catalog price'), [
                    'class' => 'btn icon add js-cpr-slideout',
                    'data-icon' => 'plus',
                    'data-store-id' => $element->storeId,
                    'data-store-handle' => $element->getStore()->handle,
                    'data-purchasable-id' => $element->id,
                    'data-asdsa' => $element->firstSave,
                ]) .
                Cp::renderTemplate('commerce/prices/_status', [
                    'areCatalogPricingJobsRunning' => Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(),
                ]);
        } else {
            /** @var Sale[] $relatedSales */
            $relatedSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($element);

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
                Cp::fieldHtml(Currency::moneyInputHtml($basePrice, [
                    'id' => 'base-price',
                    'name' => 'basePrice',
                    'currency' => $currency->getCode(),
                    'currencyLabel' => $currency->getCode(),
                    'required' => true,
                    'errors' => $element->getErrors('basePrice'),
                    'disabled' => $static,
                ]), [
                    'id' => 'base-price',
                    'label' => Craft::t('commerce', 'Price'),
                ]) .

                // Don't show base promotional price field if the system is still using sales
                ($canUseCatalogPricingRules ?
                    Cp::fieldHtml(Currency::moneyInputHtml($basePromotionalPrice, [
                        'id' => 'base-promotional-price',
                        'name' => 'basePromotionalPrice',
                        'currency' => $currency->getCode(),
                        'currencyLabel' => $currency->getCode(),
                        'errors' => $element->getErrors('basePromotionalPrice'),
                        'disabled' => $static,
                    ]), [
                        'id' => 'promotional-price',
                        'label' => Craft::t('commerce', 'Promotional Price'),
                    ]) : '') .

            Html::endTag('div') .

            // Hide the prices table if the element is a draft
            ($toggleContent ? Html::beginTag('div', ['class' => $element->getIsDraft() ? 'hidden' : '' ]) .
                Html::tag('div',
                    Html::tag('a', $toggleTitle, ['class' => 'fieldtoggle', 'data-target' => 'purchasable-toggle']) .
                    Html::beginTag('div', $toggleAttributes) .
                    Html::tag(
                        'div',
                        // Prices table
                        $toggleContent .
                        Html::endTag('div'),
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
