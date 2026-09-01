<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_taxzones` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Tax\TaxZones}
 * to read/write rows, which are then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Tax\Data\TaxAddressZone} object that the rest of the codebase
 * actually works with.
 */
class TaxZone extends BaseModel
{
    #[\Override]
    protected $table = Table::TAXZONES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'default' => 'boolean',
        'condition' => 'array',
    ];
}
