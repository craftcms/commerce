<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_site_stores` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Store\Stores} to read/write rows, which are then hydrated into (or
 * persisted from) the business {@see \CraftCms\Commerce\Store\Models\SiteStore} object that the
 * rest of the codebase actually works with.
 *
 * Keyed by `siteId` (one row per site) rather than an auto-incrementing `id`.
 */
class SiteStore extends BaseModel
{
    #[\Override]
    protected $table = Table::SITESTORES;

    #[\Override]
    protected $primaryKey = 'siteId';

    #[\Override]
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'siteId' => 'integer',
        'storeId' => 'integer',
    ];
}
