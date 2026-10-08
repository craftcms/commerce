<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static array getAllShippingRuleCategoriesData()
 * @method static array getShippingRuleCategoriesByRuleId(int $ruleId)
 * @method static array getShippingRuleCategoriesByRuleIds(int[] $ruleIds)
 * @method static bool createShippingRuleCategory(\CraftCms\Commerce\Shipping\Data\ShippingRuleCategory $model, bool $runValidation = true)
 * @method static bool deleteShippingRuleCategoryById(int $id)
 *
 * @see \CraftCms\Commerce\Shipping\ShippingRuleCategories
 */
class ShippingRuleCategories extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Shipping\ShippingRuleCategories::class;
    }
}
