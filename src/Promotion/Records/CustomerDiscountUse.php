<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_customer_discountuses` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to track per-customer discount usage counts.
 */
class CustomerDiscountUse extends BaseModel
{
    #[\Override]
    protected $table = Table::CUSTOMER_DISCOUNTUSES;

    #[\Override]
    protected $casts = [
        'customerId' => 'integer',
        'discountId' => 'integer',
        'uses' => 'integer',
    ];
}
