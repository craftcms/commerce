<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_sales` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Sales} to read/write rows, which are then hydrated into
 * (or persisted from) the business {@see \CraftCms\Commerce\Promotion\Models\Sale} object that
 * the rest of the codebase actually works with.
 */
class Sale extends BaseModel
{
    public const string APPLY_BY_PERCENT = 'byPercent';

    public const string APPLY_BY_FLAT = 'byFlat';

    public const string APPLY_TO_PERCENT = 'toPercent';

    public const string APPLY_TO_FLAT = 'toFlat';

    public const string CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    #[\Override]
    protected $table = Table::SALES;

    #[\Override]
    protected $casts = [
        'allCategories' => 'boolean',
        'allGroups' => 'boolean',
        'allPurchasables' => 'boolean',
        'dateFrom' => 'datetime',
        'dateTo' => 'datetime',
        'applyAmount' => 'float',
        'ignorePrevious' => 'boolean',
        'stopProcessing' => 'boolean',
        'enabled' => 'boolean',
        'sortOrder' => 'integer',
    ];
}
