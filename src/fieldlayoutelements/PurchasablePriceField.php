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
use craft\errors\SiteNotFoundException;
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

        $priceNamespace = $view->namespaceInputName('basePrice');
        $promotionalPriceNamespace = $view->namespaceInputName('basePromotionalPrice');
        $tableId = 'purchasable-prices-' . $element->id;
        $namespacedTableId = $view->namespaceInputId($tableId);

        $title = Json::encode($element->title);
        $js = <<<JS
(() => {
        $('input[name="$priceNamespace"], input[name="$promotionalPriceNamespace"]').on('change', function(e) {
            console.log('input[name="$priceNamespace"], input[name="$promotionalPriceNamespace"]');
            const _tableContainer = $(this).parents('.js-purchasable-price-field').find('#$namespacedTableId');
            const _loadingElements = _tableContainer.find('.js-prices-table-loading');
            _loadingElements.removeClass('hidden');
            
            Craft.sendActionRequest('POST', 'commerce/catalog-pricing/generate-catalog-prices', {
                data: {
                    purchasableId: $element->id,
                    storeId: $element->storeId,
                    basePrice: $('input[name="$priceNamespace"]').val(),
                    basePromotionalPrice: $('input[name="$promotionalPriceNamespace"]').val(),
                }
            })
            .then((response) => {
                _loadingElements.addClass('hidden');
        
                if (response.data) {
                    Object.keys(response.data).forEach((id) => {
                        const data = response.data[id];
                        let selector = '[data-catalog-pricing-rule-id="' + id + '"]';
                        selector += data.isPromotionalPrice ? '.js-purchasable-rule-promotional-price' : '.js-purchasable-rule-price';
                        const _row = _tableContainer.find(selector)
                        
                        if (_row) {
                            _row.text(data.price);
                        }
                    });
                }
        })
        .catch(({response}) => {
            _loadingElements.addClass('hidden');
            console.log(response);
            // Craft.cp.displayError(response.message);
        });; 
    });
  
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

        return Html::beginTag('div', ['class' => 'js-purchasable-price-field']) .
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
                    Html::tag('div', $this->_getCatalogPricingListByPurchasableId($element->id, $element->storeId, $tableId), ['id' => 'purchasable-prices', 'class' => 'hidden'])
                ).
            Html::endTag('div') .
        Html::endTag('div');
    }

    /**
     * @param int $purchasableId
     * @param int $storeId
     * @param string $tableId
     * @return string
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    private function _getCatalogPricingListByPurchasableId(int $purchasableId, int $storeId, string $tableId): string
    {
        $prices = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);
        $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($catalogPricingRules->isEmpty()) {
            return '';
        }

        $html = Html::beginTag('div', ['style' => ['position' => 'relative'], 'id' => $tableId]) .
        Html::beginTag('div', ['class' => 'tableview']) .
            Html::beginTag('div', ['class' => 'tablepane', 'style' => 'margin: 0']) .
            Html::beginTag('table', ['class' => 'data fullwidth']) .
                Html::beginTag('thead') .
                    Html::beginTag('tr') .
                        Html::tag('th') .
                        Html::tag('th', Craft::t('commerce', 'Price')) .
                        Html::tag('th', Craft::t('commerce', 'Promotional Price')) .
                        Html::tag('th', Craft::t('commerce', 'Store Rule')) .
                    Html::endTag('tr') .
                Html::endTag('thead') .
                Html::beginTag('tbody');


        $catalogPricingRules->each(function(CatalogPricingRule $catalogPricingRule) use (&$html, $prices, $purchasableId) {
            $html .= Html::beginTag('tr') .
                Html::tag('td', Html::a($catalogPricingRule->name, $catalogPricingRule->getCpEditUrl(),
                        $catalogPricingRule->isStoreRule()
                            ? ['target' => '_blank', 'data-icon' => 'external']
                            : ['class' => 'js-purchasable-cpr-slideout', 'data-id' => $catalogPricingRule->id, 'data-store-id' => $catalogPricingRule->storeId]
                    )
                ) .
                Html::tag(
                    'td',
                    !$catalogPricingRule->isPromotionalPrice ? $prices->firstWhere('catalogPricingRuleId', '=', $catalogPricingRule->id)?->price : '',
                    ['class' => 'js-purchasable-rule-price', 'data-purchasable-id' => $purchasableId, 'data-catalog-pricing-rule-id' => $catalogPricingRule->id]
                ) .
                Html::tag(
                    'td',
                    $catalogPricingRule->isPromotionalPrice ? $prices->firstWhere('catalogPricingRuleId', '=', $catalogPricingRule->id)?->price : '',
                    ['class' => 'js-purchasable-rule-promotional-price', 'data-purchasable-id' => $purchasableId, 'data-catalog-pricing-rule-id' => $catalogPricingRule->id]
                ) .
                Html::tag('td', $catalogPricingRule->isStoreRule() ? Html::tag('span', '', [
                    'data-icon' => 'check',
                    'title' => Craft::t('commerce', 'Yes')
                ]) : '') .
            Html::endTag('tr');
        });

        $html .= Html::endTag('tbody') .
                Html::endTag('table') .
            Html::endTag('div') .
            Html::endTag('div') .
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
        Html::endTag('div');

        $html .= Html::button(Craft::t('commerce', 'Add catalog price'), ['class' => 'btn icon add js-cpr-slideout-new', 'data-icon' => 'plus']);

        return $html;
    }
}
