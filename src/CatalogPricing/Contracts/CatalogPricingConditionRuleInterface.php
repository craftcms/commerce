<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Contracts;

use craft\db\Query;
use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;

interface CatalogPricingConditionRuleInterface extends ConditionRuleInterface
{
    /** @return string[] */
    public function getExclusiveQueryParams(): array;

    public function modifyQuery(Query $query): void;
}
