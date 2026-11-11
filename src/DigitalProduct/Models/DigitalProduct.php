<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_digitalproducts` table, written to from
 * {@see \CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct::afterSave()}.
 */
class DigitalProduct extends BaseModel
{
    #[\Override]
    protected $table = Table::DIGITALPRODUCTS;

    public $timestamps = false;

    /**
     * `id` is a foreign key to `elements.id`, not an auto-increment column.
     */
    public $incrementing = false;
}
