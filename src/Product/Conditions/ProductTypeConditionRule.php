<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Queries\ProductQuery;

use function CraftCms\Cms\t;

class ProductTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Product Type', category: 'commerce');
    }

    #[\Override]
    protected function options(): array
    {
        return collect(app(ProductTypes::class)->getAllProductTypes())
            ->map(fn(ProductType $productType) => ['value' => $productType->uid, 'label' => $productType->name])
            ->all();
    }

    public function getExclusiveQueryParams(): array
    {
        return ['type'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        $value = $this->paramValue(fn(string $value) => collect($productTypes)->firstWhere('uid', $value)?->handle);

        /** @var ProductQuery $query */
        $query->type($value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->getType()->uid);
    }
}
