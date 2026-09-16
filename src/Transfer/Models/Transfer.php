<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_transfers` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Transfer\Elements\Transfer} to read/write rows.
 */
class Transfer extends BaseModel
{
    #[\Override]
    protected $table = Table::TRANSFERS;

    public $timestamps = false;

    /**
     * `id` is a foreign key to `elements.id`, not an auto-increment column — without this,
     * Eloquent overwrites it with `lastInsertId()` (0, for a non-auto-increment PK) after insert.
     */
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'originLocationId' => 'integer',
        'destinationLocationId' => 'integer',
    ];
}
