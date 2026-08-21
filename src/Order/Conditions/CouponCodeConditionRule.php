<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use Override;
use RuntimeException;

use function CraftCms\Cms\t;

class CouponCodeConditionRule extends OrderTextValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'couponCode';

    #[Override]
    public function getLabel(): string
    {
        return t('Coupon Code', category: 'commerce');
    }

    #[Override]
    protected function matchValue(mixed $value): bool
    {
        switch ($this->operator) {
            case self::OPERATOR_EMPTY:
                return !$value;
            case self::OPERATOR_NOT_EMPTY:
                return (bool)$value;
        }

        if ($this->value === '') {
            return true;
        }

        return match ($this->operator) {
            self::OPERATOR_EQ => strcasecmp((string)$value, $this->value) === 0,
            self::OPERATOR_NE => strcasecmp((string)$value, $this->value) !== 0,
            self::OPERATOR_BEGINS_WITH => is_string($value) && str_starts_with(mb_strtolower($value), mb_strtolower($this->value)),
            self::OPERATOR_ENDS_WITH => is_string($value) && str_ends_with(mb_strtolower($value), mb_strtolower($this->value)),
            self::OPERATOR_CONTAINS => is_string($value) && str_contains(mb_strtolower($value), mb_strtolower($this->value)),
            default => throw new RuntimeException("Invalid operator: $this->operator"),
        };
    }
}
