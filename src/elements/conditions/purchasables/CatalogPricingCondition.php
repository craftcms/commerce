<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use craft\base\conditions\BaseCondition;
use craft\base\conditions\BaseConditionRule;
use craft\base\conditions\ConditionRuleInterface;
use craft\commerce\base\CatalogPricingConditionRuleInterface;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\ArrayHelper;


/**
 * Catalog Pricing Purchasable condition builder.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingCondition extends BaseCondition
{
    /**
     * @var string[] The query params that available rules shouldn’t compete with.
     */
    public array $queryParams = [];

    /**
     * @var bool
     */
    public bool $allPrices = false;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['allPrices'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return [
            CatalogPricingPurchasableConditionRule::class,
            CatalogPricingCustomerConditionRule::class,
        ];
    }

    /**
     * @inheritdoc
     */
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

        foreach ($rule->getExclusiveQueryParams() as $param) {
            if (isset($queryParams[$param])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(Query $query): void
    {
        $catalogPricingRuleIdWhere = ['or'];

        // If we are looking for all prices, we don't need to worry about the user's table
        if (!$this->allPrices) {
            $catalogPricingRuleIdWhere[] = ['catalogPricingRuleId' => null];
            $catalogPricingRuleIdWhere[] = ['catalogPricingRuleId' => (new Query())
                ->select(['cpr.id as cprid'])
                ->from([Table::CATALOG_PRICING_RULES . ' cpr'])
                ->leftJoin([Table::CATALOG_PRICING_RULES_USERS . ' cpru'], '[[cpr.id]] = [[cpru.catalogPricingRuleId]]')
                ->where(['[[cpru.id]]' => null])
                ->groupBy(['[[cpr.id]]']),
            ];
        }

        $rules = $this->getConditionRules();

        if ($customerRule = ArrayHelper::firstWhere($rules, fn(ConditionRuleInterface $rule) => $rule instanceof CatalogPricingCustomerConditionRule)) {
            /** @var CatalogPricingCustomerConditionRule $customerRule */
            // Sub query to figure out which catalog pricing rules are using user conditions
            $catalogPricingRuleIdWhere[] = ['catalogPricingRuleId' => (new Query())
                ->select(['cpr.id as cprid'])
                ->from([Table::CATALOG_PRICING_RULES . ' cpr'])
                ->leftJoin([Table::CATALOG_PRICING_RULES_USERS . ' cpru'], '[[cpr.id]] = [[cpru.catalogPricingRuleId]]')
                ->where(['[[cpru.userId]]' => $customerRule->customerId])
                ->andWhere(['not', ['[[cpru.id]]' => null]])
                ->groupBy(['[[cpr.id]]'])
            ];

            foreach ($rules as $key => $rule) {
                if ($rule instanceof CatalogPricingCustomerConditionRule) {
                    unset($rules[$key]);

                    // Can break here because there is only one customer condition rule
                    break;
                }
            }
        }

        // Deal with all prices and filtering by customer
        if (count($catalogPricingRuleIdWhere) > 1) {
            $query->andWhere($catalogPricingRuleIdWhere);
        }

        // Apply the rest of the rules
        foreach ($rules as $rule) {
            /** @var CatalogPricingConditionRuleInterface $rule */
            $rule->modifyQuery($query);
        }
    }
}
