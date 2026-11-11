<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_digitalproducttypes` table, read and written by
 * {@see \CraftCms\Commerce\DigitalProduct\DigitalProductTypes}.
 */
class DigitalProductType extends BaseModel
{
    #[\Override]
    protected $table = Table::DIGITALPRODUCTTYPES;

    public $timestamps = false;

    #[\Override]
    protected $casts = [
        'fieldLayoutId' => 'integer',
    ];
}
