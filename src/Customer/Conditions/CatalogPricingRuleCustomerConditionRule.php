<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Condition\BaseElementSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\UserQuery;
use CraftCms\Cms\User\Elements\User;
use Override;

use function CraftCms\Cms\t;

class CatalogPricingRuleCustomerConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    #[Override]
    protected function elementType(): string
    {
        return User::class;
    }

    public function getLabel(): string
    {
        return t('Customer', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['id'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var UserQuery $query */
        $query->id($this->getElementIds());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var User $element */
        return $this->matchValue($element->getId());
    }

    #[Override]
    protected function allowMultiple(): bool
    {
        return true;
    }
}
