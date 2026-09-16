<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

class ProductVariantSkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Variant SKU', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $variantQuery = Variant::find()->sku($this->paramValue());

        // getQuery() returns the raw, un-prepared builder — the sku() scope set just above is
        // only translated into a where clause by a beforeQuery callback, which hasn't run yet
        // at this point. applyBeforeQueryCallbacks() also re-applies the query's default select
        // columns, so restrict back down to the single column this subquery needs afterward.
        $variantQuery->applyBeforeQueryCallbacks();
        $variantQuery->select(['commerce_variants.primaryOwnerId as id']);
        $query->whereIn('elements.id', $variantQuery->getQuery());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $product */
        $product = $element;

        foreach ($product->getVariants() as $variant) {
            if ($this->matchValue($variant->sku)) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
