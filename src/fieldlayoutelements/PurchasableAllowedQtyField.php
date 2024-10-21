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
use yii\base\InvalidArgumentException;

/**
 * PurchasableAllowedQtyField represents an Allowed Qty field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableAllowedQtyField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Allowed Qty';

    /**
     * @inheritdoc
     */
    public bool $required = false;

    /**
     * @inheritdoc
     */
    public string $attribute = 'allowedQty';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Html::beginTag('div', ['class' => 'flex']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                Cp::textHtml([
                    'id' => 'minQty',
                    'name' => 'minQty',
                    'value' => $element->minQty,
                    'placeholder' => Craft::t('commerce', 'Any'),
                    'title' => Craft::t('commerce', 'Minimum allowed quantity'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
            Html::tag('div', Craft::t('commerce', 'to'), ['class' => 'label light']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                Cp::textHtml([
                    'id' => 'maxQty',
                    'name' => 'maxQty',
                    'value' => $element->maxQty,
                    'placeholder' => Craft::t('commerce', 'Any'),
                    'title' => Craft::t('commerce', 'Maximum allowed quantity'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
        Html::endTag('div');
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Allowed Qty');
    }
}
