<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Thin Eloquent persistence model for the `commerce_orderstatuses` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Order\OrderStatuses} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Order\Data\OrderStatus}
 * object that the rest of the codebase actually works with.
 */
class OrderStatus extends BaseModel
{
    use SoftDeletes;

    #[\Override]
    protected $table = Table::ORDERSTATUSES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'default' => 'boolean',
        'sortOrder' => 'integer',
    ];
}
