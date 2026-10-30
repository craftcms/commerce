<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_products` table.
 *
 * This holds no business logic — it's written to from
 * {@see \CraftCms\Commerce\Product\Elements\Product::afterSave()} and read back by
 * {@see \CraftCms\Commerce\Product\Queries\ProductQuery}.
 */
class Product extends BaseModel
{
    #[\Override]
    protected $table = Table::PRODUCTS;

    public $timestamps = false;

    /**
     * `id` is a foreign key to `elements.id`, not an auto-increment column — without this,
     * Eloquent overwrites it with `lastInsertId()` (0, for a non-auto-increment PK) after insert.
     */
    public $incrementing = false;
}
