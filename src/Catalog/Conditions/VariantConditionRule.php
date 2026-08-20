<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Conditions;

use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\Queries\VariantQuery;
use Override;

use function CraftCms\Cms\t;

class VariantConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
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

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var VariantQuery $query */
        $query->id($this->getElementIds());
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
    protected function elementSelectConfig(): array
    {
        return array_merge(parent::elementSelectConfig(), [
            'showSiteMenu' => true,
        ]);
    }
}
