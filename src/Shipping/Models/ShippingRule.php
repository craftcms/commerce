<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_shippingrules` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Shipping\ShippingRules} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Shipping\Data\ShippingRule}
 * object that the rest of the codebase actually works with.
 */
class ShippingRule extends BaseModel
{
    #[\Override]
    protected $table = Table::SHIPPINGRULES;

    #[\Override]
    protected $casts = [
        'methodId' => 'integer',
        'enabled' => 'boolean',
        'priority' => 'integer',
        'orderCondition' => 'array',
        'customerCondition' => 'array',
        'baseRate' => 'float',
        'perItemRate' => 'float',
        'weightRate' => 'float',
        'percentageRate' => 'float',
        'minRate' => 'float',
        'maxRate' => 'float',
    ];
}
