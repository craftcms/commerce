<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Purchasables;

use function CraftCms\Cms\t;

class PurchasableTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Purchasable Type', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['purchasableType'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->whereParam('elements.type', $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Purchasable $element */
        return $this->matchValue($element::class);
    }

    protected function options(): array
    {
        $types = [];

        foreach (app(Purchasables::class)->getAllPurchasableElementTypes() as $elementType) {
            $types[$elementType] = $elementType::displayName();
        }

        return $types;
    }
}
