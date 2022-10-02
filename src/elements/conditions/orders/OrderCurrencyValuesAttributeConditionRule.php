<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidConfigException;

/**
 * Order Number Attribute Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderCurrencyValuesAttributeConditionRule extends OrderValuesAttributeConditionRule
{
    /**
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    protected function inputHtml(): string
    {
        return
            Html::hiddenLabel(Html::encode($this->getLabel()), 'value') .
            Cp::textHtml([
                'type' => $this->inputType(),
                'id' => 'value',
                'name' => 'value',
                'value' => $this->value,
                'autocomplete' => false,
                'class' => 'flex-grow flex-shrink',
                'step' => $this->inputStep(),
            ]);
    }

    /**
     * @return string
     * @throws CurrencyException
     * @throws InvalidConfigException
     * @since 4.2.0
     */
    protected function inputStep(): string
    {
        $minorUnit = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency()->getMinorUnit();
        if ($minorUnit === 0) {
            return '1';
        }

        return '0.' . str_pad('1', $minorUnit,  '0', STR_PAD_LEFT);
    }
}
