<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Conditions;

use craft\helpers\Cp;
use CraftCms\Cms\Condition\BaseConditionRule;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface;
use Illuminate\Database\Query\Builder;
use Override;

use function CraftCms\Cms\t;

class CatalogPricingCustomerConditionRule extends BaseConditionRule implements CatalogPricingConditionRuleInterface
{
    public ?int $customerId = null;

    #[Override]
    public function getLabel(): string
    {
        return t('Customer', category: 'commerce');
    }

    #[Override]
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'customerId' => $this->customerId,
        ]);
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'customerId' => ['nullable', 'integer'],
        ]);
    }

    #[Override]
    protected function inputHtml(): string
    {
        return Html::hiddenLabel($this->getLabel(), 'customer') .
            Html::tag('div',
                Cp::elementSelectHtml([
                    'name' => 'customerId',
                    'elements' => array_filter([$this->customerId]),
                    'elementType' => User::class,
                    'sources' => null,
                    'criteria' => null,
                    'single' => true,
                ]),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }

    #[Override]
    public function getExclusiveQueryParams(): array
    {
        return ['customer'];
    }

    #[Override]
    public function modifyQuery(Builder $query): void
    {
        // Doesn't modify the query as the modification
        // of the query happens in `CatalogPricingCondition::modifyQuery()` for this rule
    }
}
