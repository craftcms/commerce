<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Thin Eloquent persistence model for the `commerce_taxcategories` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Tax\TaxCategories}
 * to read/write rows, which are then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Tax\Data\TaxCategory} object that the rest of the codebase actually
 * works with.
 */
class TaxCategory extends BaseModel
{
    use SoftDeletes;

    #[\Override]
    protected $table = Table::TAXCATEGORIES;

    #[\Override]
    protected $casts = [
        'default' => 'boolean',
    ];
}
