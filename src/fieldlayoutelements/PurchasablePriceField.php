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
use craft\commerce\models\CatalogPricing;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

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

        $view = Craft::$app->getView();

        $title = Json::encode($element->title);
        $js = <<<JS
(() => {
    $('button.js-cpr-slideout-new').on('click', function(e) {
        e.preventDefault();
        let _this = $(this);
        let slideout = new Craft.CpScreenSlideout('commerce/catalog-pricing-rules/slideout', {params: {
          storeId: $element->storeId,
          purchasableId: $element->id,
          title: $title,
        }});
        console.log(slideout);
    });

    $('a.js-purchasable-cpr-slideout').on('click', function(e) {
        e.preventDefault();
        let _this = $(this);
        let slideout = new Craft.CpScreenSlideout('commerce/catalog-pricing-rules/slideout', {params: {
          id: _this.data('id'),
          storeId: _this.data('store-id'),
          purchasableId: $element->id,
        }});
        console.log(slideout);
    });
})();
JS;
        $view->registerJs($js);

        return Html::beginTag('div', ['class' => 'flex']) .
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
                Html::tag('div', $this->_getCatalogPricingListByPurchasableId($element->id, $element->storeId), ['id' => 'purchasable-prices', 'class' => 'hidden'])
            ).
        Html::endTag('div');
    }

    /**
     * @param int $purchasableId
     * @return string
     * @throws InvalidConfigException
     */
    private function _getCatalogPricingListByPurchasableId(int $purchasableId, int $storeId): string
    {
        $prices = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);
        $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($catalogPricingRules->isEmpty()) {
            return '';
        }

        $html = Html::beginTag('div', ['class' => 'tableview']) .
            Html::beginTag('div', ['class' => 'tablepane', 'style' => 'margin: 0']) .
            Html::beginTag('table', ['class' => 'data fullwidth']) .
                Html::beginTag('thead') .
                    Html::beginTag('tr') .
                        Html::tag('th', ) .
                        Html::tag('th', Craft::t('commerce', 'Price')) .
                        Html::tag('th', Craft::t('commerce', 'Promotional Price')) .
                        Html::tag('th', Craft::t('commerce', 'Store Rule')) .
                    Html::endTag('tr') .
                Html::endTag('thead') .
                Html::beginTag('tbody');


        $catalogPricingRules->each(function(CatalogPricingRule $catalogPricingRule) use (&$html, $prices) {
            $html .= Html::beginTag('tr') .
                Html::tag('td', Html::a($catalogPricingRule->name, $catalogPricingRule->getCpEditUrl(),
                        $catalogPricingRule->isStoreRule()
                            ? ['target' => '_blank', 'data-icon' => 'external']
                            : ['class' => 'js-purchasable-cpr-slideout', 'data-id' => $catalogPricingRule->id, 'data-store-id' => $catalogPricingRule->storeId]
                    )
                ) .
                Html::tag('td', !$catalogPricingRule->isPromotionalPrice ? $prices->firstWhere('catalogPricingRuleId', '=', $catalogPricingRule->id)?->price : '') .
                Html::tag('td', $catalogPricingRule->isPromotionalPrice ? $prices->firstWhere('catalogPricingRuleId', '=', $catalogPricingRule->id)?->price : '') .
                Html::tag('td', $catalogPricingRule->isStoreRule() ? Html::tag('span', '', [
                    'data-icon' => 'check',
                    'title' => Craft::t('commerce', 'Yes')
                ]) : '') .
            Html::endTag('tr');
        });

        $html .= Html::endTag('tbody') .
                Html::endTag('table') .
            Html::endTag('div') .
            Html::endTag('div');

        $html .= Html::button(Craft::t('commerce', 'Add catalog price'), ['class' => 'btn icon add js-cpr-slideout-new', 'data-icon' => 'plus']);

        return $html;
    }
}
