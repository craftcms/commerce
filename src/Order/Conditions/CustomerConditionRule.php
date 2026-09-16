<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

/**
 * @todo Switch parent class to BaseElementSelectConditionRule in Commerce 6.0 once it supports negative matching (it currently lacks OPERATOR_NOT_IN support that this rule needs)
 */
class CustomerConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Customer', category: 'commerce');
    }

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        return [Field::make($this->getLabel(), ElementSelect::make('values')->elementType(User::class)->value($this->getValues()))];
    }

    /** @return array<never, never> */
    #[Override]
    protected function options(): array
    {
        return [];
    }

    public function getExclusiveQueryParams(): array
    {
        return ['customerId'];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $paramValue = $this->paramValue();
        if ($this->operator === self::OPERATOR_NOT_IN) {
            // A plain "not in" doesn't match a null customerId column value, so explicitly
            // include those rows too.
            $query->where(function(Builder $query) use ($paramValue) {
                $query->whereNull('commerce_orders.customerId');
                $query->whereParam('commerce_orders.customerId', $paramValue, boolean: 'or');
            });
        } else {
            $query->whereParam('commerce_orders.customerId', $paramValue);
        }
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue((string)$element->getCustomerId());
    }
}
