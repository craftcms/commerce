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
use craft\commerce\Plugin;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

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
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $basePrice = $element->getBasePrice($element->getStore());
        if (empty($element->getErrors('basePrice'))) {
            if ($basePrice === null) {
                $basePrice = 0;
            }

            $basePrice = Craft::$app->getFormatter()->asDecimal($basePrice);
        }

        $basePromotionalPrice = $element->getBasePromotionalPrice($element->getStore());
        if (empty($element->getErrors('basePromotionalPrice')) && $basePromotionalPrice !== null) {
            $basePromotionalPrice = Craft::$app->getFormatter()->asDecimal($basePromotionalPrice);
        }

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
        Html::endTag('div');
    }

    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'price';
    }
}
