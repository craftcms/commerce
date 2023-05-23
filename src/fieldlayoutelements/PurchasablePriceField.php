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
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\Plugin;
use craft\commerce\web\assets\purchasablepricefield\PurchasablePriceFieldAsset;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\assets\htmx\HtmxAsset;
use yii\base\InvalidArgumentException;

/**
 * PurchasablePriceField represents a Prie field that is included within a variant field layout designer.
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
        $name = Craft::t('commerce', '{name} catalog price', ['name' => Json::encode($element->title)]);

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
        catalogPricingRuleTempName: '$name',
        siteId: $element->siteId,
        conditionBuilderConfig: $conditionBuilderConfig,
        fieldNames: {
            price: '$priceNamespace',
            promotionalPrice: '$promotionalPriceNamespace',
        }
    });
})();
JS;
        $view->registerJs($js);

        return Html::beginTag('div', [
                'id' => 'commerce-purchasable-price-field',
                'class' => 'js-purchasable-price-field',
            ]) .
            Html::beginTag('div', ['class' => 'flex']) .
                Cp::textFieldHtml([
                    'id' => 'base-price',
                    'label' => Craft::t('commerce', 'Price') . sprintf('(%s)', Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso()),
                    'name' => 'basePrice',
                    'value' => $basePrice,
                    'placeholder' => Craft::t('commerce', 'Enter price'),
                    'required' => true,
                    'errors' => $element->getErrors('basePrice'),
                ]) .
                Cp::textFieldHtml([
                    'id' => 'promotional-price',
                    'label' => Craft::t('commerce', 'Promotional Price') . sprintf('(%s)', Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso()),
                    'name' => 'basePromotionalPrice',
                    'value' => $basePromotionalPrice,
                    'placeholder' => Craft::t('commerce', 'Enter price'),
                    'errors' => $element->getErrors('basePromotionalPrice'),
                ]) .
            Html::endTag('div') .
            Html::beginTag('div') .
                Html::tag('div',
                    Html::tag('a', 'See all prices', ['class' => 'fieldtoggle', 'data-target' => 'purchasable-prices']) .
                    Html::beginTag('div', ['class' => 'js-price-list-container', 'style' => ['position' => 'relative']]) .
                    Html::tag(
                        'div',
                        // Prices table
                        PurchasableHelper::catalogPricingRulesTableByPurchasableId($element->id, $element->storeId) .
                        Html::beginTag('div', ['class' => 'flex']) .
                            // New catalog price button
                            Html::button(Craft::t('commerce', 'Add catalog price'), [
                                'class' => 'btn icon add js-cpr-slideout',
                                'data-icon' => 'plus',
                                'data-store-id' => $element->storeId,
                                'data-store-handle' => $element->getStore()->handle,
                                'data-purchasable-id' => $element->id,
                            ]) .
                            Cp::renderTemplate('commerce/prices/_status', [
                                'areCatalogPricingJobsRunning' => Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(),
                            ]) .
                        Html::endTag('div'),
                        [
                            'id' => 'purchasable-prices',
                            'class' => 'hidden',
                        ]
                    ) .
                    Html::tag('div', '', [
                        'class' => 'js-prices-table-loading hidden',
                        'style' => [
                            'position' => 'absolute',
                            'top' => 0,
                            'left' => 0,
                            'width' => '100%',
                            'height' => '100%',
                            'background-color' => 'rgba(255, 255, 255, 0.5)',
                        ]
                    ]) .
                    Html::tag('div', Html::tag('span', '', ['class' => 'spinner']), [
                        'class' => 'js-prices-table-loading flex hidden',
                        'style' => [
                            'position' => 'absolute',
                            'top' => 0,
                            'left' => 0,
                            'width' => '100%',
                            'height' => '100%',
                            'align-items' => 'center',
                            'justify-content' => 'center',
                        ]
                    ]) .
                    Html::endTag('div')
                ).
            Html::endTag('div') .
        Html::endTag('div');
    }
}
