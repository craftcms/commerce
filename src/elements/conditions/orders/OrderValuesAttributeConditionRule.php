<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidConfigException;

/**
 * Order Number Attribute Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderValuesAttributeConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    public string $orderAttribute = '';

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return 'Label not implemented';
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->{$this->orderAttribute});
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }

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
