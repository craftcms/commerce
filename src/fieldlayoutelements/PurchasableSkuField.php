<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Variant;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\fieldlayoutelements\BaseNativeField;
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
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'sku';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $variantWithSkuFormula = $element instanceof Variant && $element->getOwner()->getType()->skuFormat !== null;
        if ($variantWithSkuFormula && $element->getIsDraft() && $this->getScenario() === Element::SCENARIO_DEFAULT) {
            return null;
        }

        return PurchasableHelper::skuInputHtml($element->getSkuAsText(), [
            'disabled' => $static,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'SKU');
    }
}
