<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

class SkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('SKU', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['sku'];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        PurchasableQuery::applySku($query, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Purchasable $element */
        return $this->matchValue($element->getSku());
    }
}
