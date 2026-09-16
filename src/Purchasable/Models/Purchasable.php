<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_purchasables` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Purchasable\Elements\Purchasable} to read/write rows.
 *
 * `id` is a foreign key to `elements.id`, not an auto-increment column — without
 * `$incrementing = false`, Eloquent overwrites it with `lastInsertId()` (0, for a
 * non-auto-increment PK) after insert, same as {@see \CraftCms\Commerce\Purchasable\Models\Donation}.
 *
 * The legacy `craft\commerce\records\Purchasable` is deleted — this was its only consumer.
 */
class Purchasable extends BaseModel
{
    #[\Override]
    protected $table = Table::PURCHASABLES;

    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
        'weight' => 'float',
        'taxCategoryId' => 'integer',
    ];
}
