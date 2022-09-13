<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\base\InvalidConfigException;

/**
 * Total Discount Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class TotalDiscountConditionRule extends OrderValuesAttributeConditionRule
{
    public string $orderAttribute = 'totalDiscount';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Total Discount');
    }

    /**
     * @inheritdoc
     */
    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            self::OPERATOR_EQ => Craft::t('app', 'equals'),
            self::OPERATOR_NE => Craft::t('app', 'does not equal'),
            self::OPERATOR_GT => Craft::t('app', 'is less than'),
            self::OPERATOR_GTE => Craft::t('app', 'is less than or equals'),
            self::OPERATOR_LT => Craft::t('app', 'is greater than'),
            self::OPERATOR_LTE => Craft::t('app', 'is greater than or equals'),
            default => $operator,
        };
    }

    /**
     * @inheritdoc
     */
    protected function paramValue(): ?string
    {
        if ($this->value === '') {
            return null;
        }

        $value = $this->value * -1;
        $value = Db::escapeParam($value);

        return "$this->operator $value";
    }

    protected function matchValue(mixed $value): bool
    {
        if ($this->value === '') {
            return true;
        }

        $ruleValue = $this->value * -1;

        return match ($this->operator) {
            self::OPERATOR_EQ => $value == $ruleValue,
            self::OPERATOR_NE => $value != $ruleValue,
            self::OPERATOR_LT => $value < $ruleValue,
            self::OPERATOR_LTE => $value <= $ruleValue,
            self::OPERATOR_GT => $value > $ruleValue,
            self::OPERATOR_GTE => $value >= $ruleValue,
            default => throw new InvalidConfigException("Invalid operator: $this->operator"),
        };
    }
}
