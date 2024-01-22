<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use Money\Currency;
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
     * @inheritdoc
     */
    protected function inputOptions(): array
    {
        return array_merge(parent::inputOptions(), [
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
        $subUnit = 2;

        if ($this->getCondition() instanceof HasStoreInterface) {
            /** @var Currency $currency */
            $currency = $this->getCondition()->getStore()->getCurrency();
            $subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($currency);
        }

        if ($subUnit === 0) {
            return '1';
        }

        return '0.' . str_pad('1', $subUnit, '0', STR_PAD_LEFT);
    }
}
