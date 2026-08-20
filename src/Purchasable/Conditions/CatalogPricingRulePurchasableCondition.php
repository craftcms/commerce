<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Cms\Element\Conditions\SiteConditionRule;
use Override;

class CatalogPricingRulePurchasableCondition extends ElementCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        $types = array_filter(parent::selectableConditionRules(), static fn($type) => !in_array($type, [
            SiteConditionRule::class,
        ], true));

        $types[] = PurchasableConditionRule::class;
        $types[] = SkuConditionRule::class;
        $types[] = PurchasableTypeConditionRule::class;
        $types[] = CatalogPricingRulePurchasableCategoryConditionRule::class;

        return $types;
    }
}
