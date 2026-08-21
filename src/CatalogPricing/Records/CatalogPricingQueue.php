<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_catalogpricing_queue` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\CatalogPricing\CatalogPricing} to read/write pending catalog-pricing
 * regeneration work.
 */
class CatalogPricingQueue extends BaseModel
{
    /**
     * Row type for purchasable-ID-based catalog pricing work.
     */
    public const string TYPE_PURCHASABLE = 'purchasable';

    /**
     * Row type for rule-ID-based (or full-regeneration) catalog pricing work.
     */
    public const string TYPE_RULE = 'rule';

    #[\Override]
    protected $table = Table::CATALOG_PRICING_QUEUE;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'ids' => 'array',
        'reserved' => 'boolean',
    ];
}
