<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_coupons` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Coupons} to read/write rows, which are then hydrated into
 * (or persisted from) the business {@see \CraftCms\Commerce\Promotion\Models\Coupon} object that
 * the rest of the codebase actually works with.
 */
class Coupon extends BaseModel
{
    #[\Override]
    protected $table = Table::COUPONS;

    #[\Override]
    protected $casts = [
        'discountId' => 'integer',
        'uses' => 'integer',
        'maxUses' => 'integer',
    ];
}
