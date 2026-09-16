<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Conditions;

use CraftCms\Commerce\Product\Variant\Conditions\VariantCondition;
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
