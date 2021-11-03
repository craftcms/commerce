<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\helpers\VariantMatrix;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

/**
 * VariantsField represents a Variants field that can be included within a product type’s product field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.0
 */
class VariantsField extends BaseField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'variants';
    }

    /**
     * @inheritdoc
     */
    public function mandatory(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasCustomWidth(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false)
    {
        return Craft::t('commerce', 'Variants');
    }

    /**
     * @inheritdoc
     */
    protected function selectorInnerHtml(): string
    {
        return
            Html::tag('span', '', [
                'class' => ['fld-variants-field-icon', 'fld-field-hidden', 'hidden'],
            ]) .
            parent::selectorInnerHtml();
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false)
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException('ProductTitleField can only be used in product field layouts.');
        }

        $type = $element->getType();

        if (!$type->hasVariants) {
            return null;
        }

        return VariantMatrix::getVariantMatrixHtml($element);
    }
}
