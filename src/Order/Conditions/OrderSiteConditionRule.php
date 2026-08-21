<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;

use function CraftCms\Cms\t;

class OrderSiteConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Order Site', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['orderSiteId'];
    }

    protected function options(): array
    {
        return Sites::getAllSites()->pluck('name', 'id')->all();
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->orderSiteId($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue((string)$element->orderSiteId);
    }
}
