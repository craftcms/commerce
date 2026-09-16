<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Database\Query\Builder;

/**
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderValuesAttributeConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public string $orderAttribute = '';

    public function getExclusiveQueryParams(): array
    {
        return [$this->orderAttribute];
    }

    public function getLabel(): string
    {
        return 'Label not implemented';
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->{$this->orderAttribute});
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        OrderQuery::{'apply' . ucfirst($this->orderAttribute)}($query, $this->paramValue());
    }
}
