<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Conditions;

use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

class VariantConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    protected function elementType(): string
    {
        return Variant::class;
    }

    public function getLabel(): string
    {
        return t('Product Variant', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['id'];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        ElementQuery::applyId($query, $this->getElementIds());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Variant $element */
        return $this->matchValue($element->id);
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
