<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_discount_categories` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to read/write the category relations for a
 * discount.
 */
class DiscountCategory extends BaseModel
{
    #[\Override]
    protected $table = Table::DISCOUNT_CATEGORIES;

    #[\Override]
    protected $casts = [
        'discountId' => 'integer',
        'categoryId' => 'integer',
    ];
}
