<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_email_discountuses` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to track per-email discount usage counts.
 */
class EmailDiscountUse extends BaseModel
{
    #[\Override]
    protected $table = Table::EMAIL_DISCOUNTUSES;

    #[\Override]
    protected $casts = [
        'discountId' => 'integer',
        'uses' => 'integer',
    ];
}
