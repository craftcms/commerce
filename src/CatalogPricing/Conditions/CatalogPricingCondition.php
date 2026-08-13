<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Conditions;

use craft\helpers\ArrayHelper;
use CraftCms\Cms\Condition\BaseCondition;
use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;
use CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Query\Builder;
use Override;

class CatalogPricingCondition extends BaseCondition
{
    /**
     * @var string[] The query params that available rules shouldn’t compete with.
     */
    public array $queryParams = [];

    public bool $allPrices = false;

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'allPrices' => ['boolean'],
        ]);
    }

    #[Override]
    protected function selectableConditionRules(): array
    {
        return [
            CatalogPricingPurchasableConditionRule::class,
            CatalogPricingCustomerConditionRule::class,
        ];
    }

    #[Override]
    protected function isConditionRuleSelectable(ConditionRuleInterface $rule): bool
    {
        if (!parent::isConditionRuleSelectable($rule)) {
            return false;
        }

        // Make sure the rule doesn't conflict with the existing params
        $queryParams = array_merge($this->queryParams);
        foreach ($this->getConditionRules() as $existingRule) {
            /** @var CatalogPricingConditionRuleInterface $existingRule */
            array_push($queryParams, ...$existingRule->getExclusiveQueryParams());
        }

        $queryParams = array_flip($queryParams);

        if (method_exists($rule, 'getExclusiveQueryParams')) {
            foreach ($rule->getExclusiveQueryParams() as $param) {
                if (isset($queryParams[$param])) {
                    return false;
                }
            }
        }

        return true;
    }

    #[Override]
    public function config(): array
    {
        $config = parent::config();
        $config['allPrices'] = $this->allPrices;

        return $config;
    }

    public function modifyQuery(Builder $query): void
    {
        $rules = $this->getConditionRules();

        /** @var CatalogPricingCustomerConditionRule|null $customerRule */
        $customerRule = ArrayHelper::firstWhere($rules, fn(ConditionRuleInterface $rule) => $rule instanceof CatalogPricingCustomerConditionRule);

        if ($customerRule) {
            foreach ($rules as $key => $rule) {
                if ($rule instanceof CatalogPricingCustomerConditionRule) {
                    unset($rules[$key]);

                    // Can break here because there is only one customer condition rule
                    break;
                }
            }
        }

        $hasRestriction = !$this->allPrices || $customerRule !== null;

        if ($hasRestriction) {
            $query->where(function(Builder $q) use ($customerRule) {
                // If we are looking for all prices, we don't need to worry about the user's table
                if (!$this->allPrices) {
                    $q->orWhereNull('catalogPricingRuleId');
                    $q->orWhereIn('catalogPricingRuleId', function(Builder $sub) {
                        $sub->select('cpr.id')
                            ->from(Table::CATALOG_PRICING_RULES . ' as cpr')
                            ->leftJoin(Table::CATALOG_PRICING_RULES_USERS . ' as cpru', 'cpr.id', '=', 'cpru.catalogPricingRuleId')
                            ->whereNull('cpru.id')
                            ->groupBy('cpr.id');
                    });
                }

                if ($customerRule) {
                    // Sub query to figure out which catalog pricing rules are using user conditions
                    $q->orWhereIn('catalogPricingRuleId', function(Builder $sub) use ($customerRule) {
                        $sub->select('cpr.id')
                            ->from(Table::CATALOG_PRICING_RULES . ' as cpr')
                            ->leftJoin(Table::CATALOG_PRICING_RULES_USERS . ' as cpru', 'cpr.id', '=', 'cpru.catalogPricingRuleId')
                            ->where('cpru.userId', $customerRule->customerId)
                            ->whereNotNull('cpru.id')
                            ->groupBy('cpr.id');
                    });
                }
            });
        }

        // Apply the rest of the rules
        foreach ($rules as $rule) {
            /** @var CatalogPricingConditionRuleInterface $rule */
            $rule->modifyQuery($query);
        }
    }
}
