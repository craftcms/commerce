<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\conditions\ConditionRuleInterface;
use craft\db\Query;

/**
 * CatalogPricingConditionRuleInterface defines the common interface to be implemented by catalog pricing condition rule classes.
 *
 * @property-read string[] $exclusiveQueryParams The query param names that this rule should have exclusive control over
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
interface CatalogPricingConditionRuleInterface extends ConditionRuleInterface
{
    /**
     * Returns the query param names that this rule should have exclusive control over.
     *
     * @return string[]
     */
    public function getExclusiveQueryParams(): array;

    /**
     * Modifies the given query with the condition rule.
     *
     * @param Query $query
     */
    public function modifyQuery(Query $query): void;
}
