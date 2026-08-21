<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Conditions;

use Override;

class CatalogPricingRuleVariantCondition extends VariantCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            CatalogPricingRuleVariantConditionRule::class,
        ]);
    }
}
