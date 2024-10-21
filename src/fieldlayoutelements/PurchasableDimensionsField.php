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
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

/**
 * PurchasableDimensionsField represents the dimensions field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableDimensionsField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Dimensions';

    /**
     * @inheritdoc
     */
    public string $attribute = 'dimensions';

    /**
     * @inheritdoc
     */
    protected function showLabel(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Variant && !$element->getOwner()->getType()->hasDimensions) {
            return false;
        }

        return parent::showInForm($element);
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Html::beginTag('div' , ['class' => 'flex']) .
            Cp::fieldHtml(Cp::textHtml([
                'id' => 'length',
                'name' => 'length',
                'value' => $element->length !== null ? Craft::$app->getFormattingLocale()->getFormatter()->asDecimal($element->length) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => Plugin::getInstance()->getSettings()->dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'length', 'label' => Craft::t('commerce', 'Length')]) .
            Cp::fieldHtml(Cp::textHtml([
                'id' => 'width',
                'name' => 'width',
                'value' => $element->width !== null ? Craft::$app->getFormattingLocale()->getFormatter()->asDecimal($element->width) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => Plugin::getInstance()->getSettings()->dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'width', 'label' => Craft::t('commerce', 'Width')]) .
            Cp::fieldHtml(Cp::textHtml([
                'id' => 'height',
                'name' => 'height',
                'value' => $element->height !== null ? Craft::$app->getFormattingLocale()->getFormatter()->asDecimal($element->height) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => Plugin::getInstance()->getSettings()->dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'height', 'label' => Craft::t('commerce', 'Height')]) .
        Html::endTag('div');
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Dimensions');
    }
}
