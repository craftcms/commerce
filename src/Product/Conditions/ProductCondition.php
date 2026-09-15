<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Commerce\Product\Elements\Product;
use Override;

class ProductCondition extends ElementCondition
{
    #[Override]
    public ?string $elementType = Product::class;

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            ProductTypeConditionRule::class,
            ProductVariantSearchConditionRule::class,
            ProductVariantSkuConditionRule::class,
            ProductVariantStockConditionRule::class,
            ProductVariantPriceConditionRule::class,
        ]);
    }
}
