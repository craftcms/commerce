<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_sale_purchasables` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Sales} to read/write the purchasable relations for a sale.
 */
class SalePurchasable extends BaseModel
{
    #[\Override]
    protected $table = Table::SALE_PURCHASABLES;

    #[\Override]
    protected $casts = [
        'saleId' => 'integer',
        'purchasableId' => 'integer',
    ];
}
