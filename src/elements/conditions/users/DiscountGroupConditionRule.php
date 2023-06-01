<?php

namespace craft\commerce\elements\conditions\users;

use craft\elements\conditions\users\GroupConditionRule;
use craft\elements\db\ElementQueryInterface;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;

/**
 * Discount user group condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class DiscountGroupConditionRule extends GroupConditionRule
{

    protected const OPERATOR_IN_ALL = 'inAll';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return \Craft::t('app', 'User Groups');
    }

    /**
     * @inheritDoc
     */
    protected function operators(): array
    {
        return array_merge(parent::operators(), [
            self::OPERATOR_IN_ALL,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            self::OPERATOR_IN_ALL => 'is in all of',
            default => parent::operatorLabel($operator)
        };
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount user group rule does not support element queries.');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * Returns whether the condition rule matches the given value.
     *
     * @param string|string[]|null $value
     * @return bool
     */
    protected function matchValue(array|string|null $value): bool
    {
        if (!$this->getValues()) {
            return true;
        }

        if ($value === '' || $value === null) {
            $value = [];
        } else {
            $value = (array)$value;
        }

        return match ($this->operator) {
            self::OPERATOR_IN => !empty(array_intersect($value, $this->getValues())),
            self::OPERATOR_NOT_IN => empty(array_intersect($value, $this->getValues())),
            self::OPERATOR_IN_ALL => empty(array_diff($this->getValues(), $value)),
            default => throw new InvalidConfigException("Invalid operator: $this->operator"),
        };
    }
}
