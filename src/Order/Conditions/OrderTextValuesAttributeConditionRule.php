<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;

/**
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderTextValuesAttributeConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
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

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }
}
