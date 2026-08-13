<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_discount_purchasables` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to read/write the purchasable relations for a
 * discount.
 */
class DiscountPurchasable extends BaseModel
{
    #[\Override]
    protected $table = Table::DISCOUNT_PURCHASABLES;

    #[\Override]
    protected $casts = [
        'discountId' => 'integer',
        'purchasableId' => 'integer',
    ];
}
