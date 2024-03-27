<?php

namespace craft\commerce\elements\conditions\addresses;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\NotSupportedException;

/**
 * Total Price Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 */
class PostalCodeFormulaConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Postal Code Formula');
    }

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
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Address Condition does not support element queries.');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Address $address */
        $address = $element;
        $formulasService = Plugin::getInstance()->getFormulas();
        $formula = $this->value;
        $postalCode = $address->postalCode;

        $result = (bool)$formulasService->evaluateCondition($formula, ['postalCode' => $postalCode], 'Postal code formula matching address');

        if (!$result) {
            return false;
        }

        return true;
    }

    public function operators(): array
    {
        return [
            self::OPERATOR_EQ,
        ];
    }

    public function inputHtml(): string
    {
        return Html::hiddenLabel($this->getLabel(), 'value') .
            Cp::textareaHtml([
                'type' => $this->inputType(),
                'id' => 'value',
                'name' => 'value',
                'code' => 'value',
                'value' => $this->value,
                'autocomplete' => false,
                'class' => 'fullwidth code',
            ]);
    }
}
