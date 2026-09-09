<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Conditions;

use CraftCms\Cms\Condition\BaseLightswitchConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

use function CraftCms\Cms\t;

class ProductVariantInventoryTrackedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Variant Tracks Stock', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['variantStock'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.primaryOwnerId as id']);
        $variantQuery->inventoryTracked($this->value);

        $query->whereIn('elements.id', $variantQuery->getQuery());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $product */
        $product = $element;

        foreach ($product->getVariants() as $variant) {
            if ($this->matchValue($variant->inventoryTracked)) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
