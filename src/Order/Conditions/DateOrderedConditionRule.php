<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseDateRangeConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;

use function CraftCms\Cms\t;

class DateOrderedConditionRule extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Date Ordered', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['dateOrdered'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->dateOrdered($this->queryParamValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue($element->dateOrdered);
    }
}
