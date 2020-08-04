<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use craft\base\ElementInterface;
use craft\commerce\elements\Variant;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

/**
 * VariantTitleField represents a Title field that can be included within a product type’s variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.0
 */
class VariantTitleField extends TitleField
{
    /**
     * @inheritdoc
     */
    protected function selectorInnerHtml(): string
    {
        return
            Html::tag('span', '', [
                'class' => ['fld-variant-title-field-icon', 'fld-field-hidden', 'hidden'],
            ]) .
            parent::selectorInnerHtml();
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false)
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException('VariantTitleField can only be used in variant field layouts.');
        }

        if (!$element->getProduct()->getType()->hasVariantTitleField && !$element->hasErrors('title')) {
            return null;
        }

        return parent::inputHtml($element, $static);
    }
}
