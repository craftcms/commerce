<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Support\Query;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

class TotalDiscountConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'totalDiscount';

    #[Override]
    public function getLabel(): string
    {
        return t('Total Discount', category: 'commerce');
    }

    #[Override]
    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            self::OPERATOR_EQ => t('equals'),
            self::OPERATOR_NE => t('does not equal'),
            self::OPERATOR_GT => t('is less than'),
            self::OPERATOR_GTE => t('is less than or equals'),
            self::OPERATOR_LT => t('is greater than'),
            self::OPERATOR_LTE => t('is greater than or equals'),
            default => $operator,
        };
    }

    #[Override]
    protected function paramValue(): ?string
    {
        if ($this->value === '') {
            return null;
        }

        $value = $this->value;
        if (is_numeric($value)) {
            $value = (string)((float)$value * -1);
        }

        $value = Query::escapeParam($value);

        return "$this->operator $value";
    }

    #[Override]
    protected function matchValue(mixed $value): bool
    {
        if ($this->value === '') {
            return true;
        }

        $ruleValue = $this->value;
        if (is_numeric($ruleValue)) {
            $ruleValue = (float)$ruleValue * -1;
        }

        return match ($this->operator) {
            self::OPERATOR_EQ => $value == $ruleValue,
            self::OPERATOR_NE => $value != $ruleValue,
            self::OPERATOR_LT => $value < $ruleValue,
            self::OPERATOR_LTE => $value <= $ruleValue,
            self::OPERATOR_GT => $value > $ruleValue,
            self::OPERATOR_GTE => $value >= $ruleValue,
            default => throw new RuntimeException("Invalid operator: $this->operator"),
        };
    }
}
