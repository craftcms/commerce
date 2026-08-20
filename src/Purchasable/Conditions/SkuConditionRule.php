<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;

use function CraftCms\Cms\t;

class SkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('SKU', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['sku'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var PurchasableQuery $query */
        $query->sku($this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Purchasable $element */
        return $this->matchValue($element->getSku());
    }
}
