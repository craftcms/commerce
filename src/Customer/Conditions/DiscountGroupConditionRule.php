<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\User\Conditions\GroupConditionRule;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

class DiscountGroupConditionRule extends GroupConditionRule
{
    protected const string OPERATOR_IN_ALL = 'inAll';

    public function getLabel(): string
    {
        return t('User Groups', category: 'app');
    }

    #[Override]
    protected function operators(): array
    {
        return array_merge(parent::operators(), [
            self::OPERATOR_IN_ALL,
        ]);
    }

    #[Override]
    protected function operatorLabel(string $operator): string
    {
        return match ($operator) {
            self::OPERATOR_IN_ALL => t('is in all of'),
            default => parent::operatorLabel($operator),
        };
    }

    #[Override]
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new RuntimeException('Discount user group rule does not support element queries.');
    }

    #[Override]
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    #[Override]
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
            default => throw new RuntimeException("Invalid operator: $this->operator"),
        };
    }
}
