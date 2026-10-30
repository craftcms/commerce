<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_shippingrule_categories` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Shipping\ShippingRuleCategories} to read/write rows, which are then
 * hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Shipping\Data\ShippingRuleCategory} object that the rest of the
 * codebase actually works with.
 */
class ShippingRuleCategory extends BaseModel
{
    public const string CONDITION_ALLOW = 'allow';

    public const string CONDITION_DISALLOW = 'disallow';

    public const string CONDITION_REQUIRE = 'require';

    #[\Override]
    protected $table = Table::SHIPPINGRULE_CATEGORIES;

    #[\Override]
    protected $casts = [
        'shippingRuleId' => 'integer',
        'shippingCategoryId' => 'integer',
        'perItemRate' => 'float',
        'weightRate' => 'float',
        'percentageRate' => 'float',
    ];
}
