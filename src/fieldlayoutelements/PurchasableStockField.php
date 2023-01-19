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
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\View;
use yii\base\InvalidArgumentException;

/**
 * PurchasableStockField represents a Stock field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableStockField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Stock';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'stock';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        Craft::$app->getView()->registerJs('$(".unlimited-stock").on("change", (ev) => {
            if ($(ev.target).is(":checked")) {
                $(ev.target).closest(".stock-wrapper").find("#stock").prop("disabled", true).addClass("disabled");
            } else {
                $(ev.target).closest(".stock-wrapper").find("#stock").prop("disabled", false).removeClass("disabled");
            }
        });', View::POS_READY);

        return Html::beginTag('div', ['class' => 'flex stock-wrapper']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
            Cp::textHtml([
                'id' => 'stock',
                'name' => 'stock',
                'value' => ($element->hasUnlimitedStock || $element->stock === null ? '' : $element->stock),
                'placeholder' => Craft::t('commerce', 'Enter stock'),
                'disabled' => $element->hasUnlimitedStock,
            ]) .
            Html::endTag('div') .
            Html::beginTag('div', ['class' => 'nowrap']) .
                Html::checkbox('hasUnlimitedStock', $element->hasUnlimitedStock, [
                    'id' => 'unlimited-stock',
                    'class' => 'unlimited-stock',
                    'label' => Craft::t('commerce', 'Unlimited'),
                ]) .
            Html::endTag('div') .
        Html::endTag('div');
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Stock');
    }
}
