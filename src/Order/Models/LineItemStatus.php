<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_lineitemstatuses` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Order\LineItemStatuses} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Order\Data\LineItemStatus}
 * object that the rest of the codebase actually works with.
 */
class LineItemStatus extends BaseModel
{
    #[\Override]
    protected $table = Table::LINEITEMSTATUSES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'default' => 'boolean',
        'isArchived' => 'boolean',
        'sortOrder' => 'integer',
        'dateArchived' => 'datetime',
    ];
}
