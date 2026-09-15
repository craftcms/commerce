<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Shipping\Data\BaseShippingMethod;
use CraftCms\Commerce\Shipping\ShippingMethods;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

class ShippingMethodConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Shipping Method', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    protected function options(): array
    {
        return app(ShippingMethods::class)->getAllShippingMethods()->mapWithKeys(fn(BaseShippingMethod $method) => [$method->handle => $method->name])->all();
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        /** @var OrderQuery $elementQuery */
        $elementQuery->shippingMethodHandle($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue($element->shippingMethodHandle);
    }
}
