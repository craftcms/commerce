<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_storesettings` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Store\StoreSettings} to read/write rows, which are then hydrated into
 * (or persisted from) the business {@see \CraftCms\Commerce\Store\Models\StoreSettings} object
 * that the rest of the codebase actually works with.
 *
 * Keyed by `id` matching the owning store's ID (one row per store) rather than its own
 * auto-incrementing sequence.
 */
class StoreSettings extends BaseModel
{
    #[\Override]
    protected $table = Table::STORESETTINGS;

    #[\Override]
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'locationAddressId' => 'integer',
        'countries' => 'array',
        'marketAddressCondition' => 'array',
    ];
}
