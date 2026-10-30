<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_catalogpricingrules` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\CatalogPricing\CatalogPricingRules} to read/write rows, which are
 * then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule} object that the rest of the
 * codebase actually works with.
 */
class CatalogPricingRule extends BaseModel
{
    public const string APPLY_BY_PERCENT = 'byPercent';

    public const string APPLY_BY_FLAT = 'byFlat';

    public const string APPLY_TO_PERCENT = 'toPercent';

    public const string APPLY_TO_FLAT = 'toFlat';

    public const string APPLY_PRICE_TYPE_PRICE = 'price';

    public const string APPLY_PRICE_TYPE_PROMOTIONAL_PRICE = 'promotionalPrice';

    #[\Override]
    protected $table = Table::CATALOG_PRICING_RULES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'applyAmount' => 'float',
        'dateFrom' => 'datetime',
        'dateTo' => 'datetime',
        'enabled' => 'boolean',
        'isPromotionalPrice' => 'boolean',
        'customerCondition' => 'array',
        'productCondition' => 'array',
        'variantCondition' => 'array',
        'purchasableCondition' => 'array',
        'metadata' => 'array',
    ];
}
