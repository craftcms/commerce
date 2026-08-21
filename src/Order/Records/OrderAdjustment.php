<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_orderadjustments` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Order\OrderAdjustments} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Order\Models\OrderAdjustment}
 * object that the rest of the codebase actually works with.
 */
class OrderAdjustment extends BaseModel
{
    #[\Override]
    protected $table = Table::ORDERADJUSTMENTS;

    #[\Override]
    protected $casts = [
        'orderId' => 'integer',
        'lineItemId' => 'integer',
        'amount' => 'float',
        'included' => 'boolean',
        'isEstimated' => 'boolean',
        'sourceSnapshot' => 'array',
    ];
}
