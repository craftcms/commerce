<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_stores` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Store\Stores} to read/write rows, which are then hydrated into (or
 * persisted from) the business {@see \CraftCms\Commerce\Store\Data\Store} object that the rest
 * of the codebase actually works with.
 */
class Store extends BaseModel
{
    #[\Override]
    protected $table = Table::STORES;

    #[\Override]
    protected $casts = [
        'primary' => 'boolean',
        'sortOrder' => 'integer',
    ];
}
