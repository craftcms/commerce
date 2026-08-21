<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_ordernotices` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Order\Elements\Order} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Order\Models\OrderNotice}
 * object that the rest of the codebase actually works with.
 */
class OrderNotice extends BaseModel
{
    #[\Override]
    protected $table = Table::ORDERNOTICES;

    #[\Override]
    protected $casts = [
        'orderId' => 'integer',
    ];
}
