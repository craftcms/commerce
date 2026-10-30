<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Element\Conditions\SiteConditionRule;
use CraftCms\Cms\User\Conditions\LastLoginDateConditionRule;
use CraftCms\Cms\User\Conditions\UserCondition;
use Override;

class CatalogPricingRuleCustomerCondition extends UserCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(
            array_values(array_filter(
                parent::selectableConditionRules(),
                static fn(string|array $type) => !in_array(is_string($type) ? $type : $type['class'], [
                    // Remove rules that don't make sense in this context
                    LastLoginDateConditionRule::class,
                    SiteConditionRule::class,
                ], true)
            )),
            // Add additional rules
            [
                CatalogPricingRuleCustomerConditionRule::class,
            ]
        );
    }
}
