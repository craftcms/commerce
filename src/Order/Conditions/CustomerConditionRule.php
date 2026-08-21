<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

/**
 * @todo Switch parent class to BaseElementSelectConditionRule in Commerce 6.0 once it supports negative matching (it currently lacks OPERATOR_NOT_IN support that this rule needs)
 */
class CustomerConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Customer', category: 'commerce');
    }

    #[Override]
    protected function inputHtml(): string
    {
        $users = User::find()->status(null)->limit(null)->id($this->values)->all();

        return Cp::elementSelectHtml([
            'name' => 'values',
            'elements' => $users,
            'elementType' => User::class,
            'sources' => null,
            'criteria' => null,
            'condition' => null,
            'single' => false,
        ]);
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

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $paramValue = $this->paramValue();
        if ($this->operator === self::OPERATOR_NOT_IN) {
            // Account for the fact that querying using a combination of `not` and `in` doesn't match `null` in the column
            $query->whereParam(DB::raw('coalesce(commerce_orders.customerId, -1)'), $paramValue);
        } else {
            $query->customerId($paramValue);
        }
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue((string)$element->getCustomerId());
    }
}
