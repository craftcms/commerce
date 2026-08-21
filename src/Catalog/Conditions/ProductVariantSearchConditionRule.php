<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use Override;

use function CraftCms\Cms\t;

class ProductVariantSearchConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Variant Search', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    #[Override]
    protected function operators(): array
    {
        return [];
    }

    /**
     * Returns the raw search value.
     *
     * Note we can't use {@see paramValue()} here because it prepends the operator
     * (e.g. `=`) intended for {@see \CraftCms\Cms\Support\Query::whereParam()}, which would
     * corrupt the value once it's passed to {@see \CraftCms\Cms\Element\Queries\Concerns\SearchesElements::search()}.
     */
    private function searchValue(): string
    {
        return trim($this->value);
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.primaryOwnerId as id']);
        $variantQuery->search($this->searchValue());

        $query->whereIn('elements.id', $variantQuery->getQuery());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        $variantIds = $element->getVariants()->pluck('id')->all();
        if (empty($variantIds)) {
            return false;
        }

        // Perform a variant query search to ensure it is the same process as modifyQuery()
        $variantQuery = Variant::find();
        $variantQuery->search($this->searchValue());
        $variantQuery->id($variantIds);

        return $variantQuery->count() > 0;
    }
}
