<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Conditions;

use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;

use function CraftCms\Cms\t;

class VariantProductConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    protected function elementType(): string
    {
        return Product::class;
    }

    public function getLabel(): string
    {
        return t('Product', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['product', 'productId', 'primaryOwnerId', 'primaryOwner', 'owner', 'ownerId'];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $ownerIds = $this->getElementIds();
        if (empty($ownerIds)) {
            return;
        }

        // Mirrors the ownerId-only branch of core's QueriesNestedElements::initQueriesNestedElements()
        // — the fieldId/primaryOwnerId/draft-and-revision-owner handling in the rest of that method
        // doesn't apply here, since this rule only ever narrows by ownerId.
        $query->whereIn('elements.id', DB::table(Table::ELEMENTS_OWNERS)
            ->select('elementId')
            ->whereIn('ownerId', $ownerIds));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Variant $element */
        return $this->matchValue($element->getOwnerId());
    }

    #[Override]
    protected function allowMultiple(): bool
    {
        return true;
    }

    #[Override]
    protected function elementSelect(): ElementSelect
    {
        return parent::elementSelect()->showSiteMenu();
    }
}
