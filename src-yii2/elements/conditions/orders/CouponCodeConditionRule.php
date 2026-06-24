<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\helpers\StringHelper;
use yii\base\InvalidConfigException;

/**
 * Order Coupon Code condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class CouponCodeConditionRule extends OrderTextValuesAttributeConditionRule
{
    public string $orderAttribute = 'couponCode';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Coupon Code');
    }

    /**
     * @inheritdoc
     */
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
            self::OPERATOR_EQ => strcasecmp($value, $this->value) === 0,
            self::OPERATOR_NE => strcasecmp($value, $this->value) !== 0,
            self::OPERATOR_BEGINS_WITH => is_string($value) && StringHelper::startsWith($value, $this->value, false),
            self::OPERATOR_ENDS_WITH => is_string($value) && StringHelper::endsWith($value, $this->value, false),
            self::OPERATOR_CONTAINS => is_string($value) && StringHelper::contains($value, $this->value, false),
            default => throw new InvalidConfigException("Invalid operator: $this->operator"),
        };
    }
}
