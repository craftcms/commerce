<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_sale_entries` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Sales} to read/write the entry relations for a sale.
 */
class SaleEntry extends BaseModel
{
    #[\Override]
    protected $table = Table::SALE_ENTRIES;

    #[\Override]
    protected $casts = [
        'saleId' => 'integer',
        'entryId' => 'integer',
    ];
}
