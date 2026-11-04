<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_discount_entries` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to read/write the entry relations for a
 * discount.
 */
class DiscountEntry extends BaseModel
{
    #[\Override]
    protected $table = Table::DISCOUNT_ENTRIES;

    #[\Override]
    protected $casts = [
        'discountId' => 'integer',
        'entryId' => 'integer',
    ];
}
