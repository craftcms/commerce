<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule;
use Override;

class VariantCondition extends ElementCondition
{
    #[Override]
    public ?string $elementType = Variant::class;

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            VariantProductConditionRule::class,
            SkuConditionRule::class,
        ]);
    }
}
