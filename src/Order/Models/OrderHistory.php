<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_orderhistories` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Order\OrderHistories} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Order\Data\OrderHistory}
 * object that the rest of the codebase actually works with.
 */
class OrderHistory extends BaseModel
{
    #[\Override]
    protected $table = Table::ORDERHISTORIES;

    #[\Override]
    protected $casts = [
        'orderId' => 'integer',
        'userId' => 'integer',
        'prevStatusId' => 'integer',
        'newStatusId' => 'integer',
    ];
}
