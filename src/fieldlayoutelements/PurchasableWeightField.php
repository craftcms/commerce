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
use yii\base\InvalidArgumentException;

/**
 * PurchasableWeightField represents a Weight field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableWeightField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Weight';

    /**
     * @inheritdoc
     */
    public string $attribute = 'weight';

    /**
     * @inheritdoc
     */
    protected function showLabel(): bool
    {
        return true;
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

        return Cp::textHtml([
            'id' => 'weight',
            'name' => 'weight',
            'value' => $element->weight !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($element->weight) : '',
            'class' => 'text',
            'size' => 10,
            'unit' => Plugin::getInstance()->getSettings()->weightUnits,
            'placeholder' => Craft::t('commerce', 'Weight'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Weight');
    }
}
