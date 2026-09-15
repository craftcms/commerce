<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;

class ProductTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
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

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        $value = $this->paramValue(fn(string $value) => collect($productTypes)->firstWhere('uid', $value)?->handle);

        // Mirrors ProductQuery::type()'s handle-resolution branch, applied directly to the
        // query builder — see CustomerConditionRule for why setting the scope property on
        // $elementQuery here wouldn't work.
        $typeIds = DB::table(Table::PRODUCTTYPES)->whereParam('handle', $value)->pluck('id')->all();

        $query->whereIn('commerce_products.typeId', $typeIds);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->getType()->uid);
    }
}
