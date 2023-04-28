<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use craft\base\conditions\BaseCondition;
use craft\base\conditions\ConditionRuleInterface;
use craft\commerce\base\CatalogPricingConditionRuleInterface;
use craft\db\Query;


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
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return [
            CatalogPricingPurchasableConditionRule::class,
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
        foreach ($this->getConditionRules() as $rule) {
            /** @var CatalogPricingConditionRuleInterface $rule */
            $rule->modifyQuery($query);
        }
    }
}
