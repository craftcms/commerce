<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Contracts;

use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;
use Illuminate\Database\Query\Builder;

interface CatalogPricingConditionRuleInterface extends ConditionRuleInterface
{
    /** @return string[] */
    public function getExclusiveQueryParams(): array;

    public function modifyQuery(Builder $query): void;
}
