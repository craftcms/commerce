<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseLightswitchConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

class PaidConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Paid', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['paid'];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        /** @var OrderQuery $elementQuery */
        if ($this->value) {
            $elementQuery->isPaid();
        } else {
            $elementQuery->isUnpaid();
        }
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->value ? $element->getIsPaid() : $element->getIsUnpaid();
    }
}
