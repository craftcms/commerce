<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;

use function CraftCms\Cms\t;

class ProductVariantStockConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Variant Stock', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['variantStock'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.primaryOwnerId as id']);
        $variantQuery->inventoryTracked(true);
        $variantQuery->stock($this->paramValue());

        $query->whereIn('elements.id', $variantQuery->getQuery());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $product */
        $product = $element;

        foreach ($product->getVariants() as $variant) {
            if (!$variant::hasInventory()) {
                return true;
            }

            if ($variant->inventoryTracked === true && $this->matchValue($variant->getStock())) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
