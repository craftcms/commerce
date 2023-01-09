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
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

/**
 * PurchasableSkuField represents an SKU field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableSkuField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'SKU';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $html = '';

        if ($element instanceof Variant && $element->getProduct()->getType()->skuFormat !== null && !$element->id) {
            // @TODO work out where SKU format will be defined
            $html .= Html::hiddenInput('sku', '');
        } else {
            $html .= Cp::textHtml([
                'id' => 'sku',
                'name' => 'sku',
                'value' => $element->getSkuAsText(),
                'placeholder' => Craft::t('commerce', 'Enter SKU'),
                'class' => 'code',
            ]);
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'SKU');
    }

    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'sku';
    }
}
