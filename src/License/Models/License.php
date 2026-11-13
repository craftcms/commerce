<?php

declare(strict_types=1);

namespace CraftCms\Commerce\License\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_licenses` table, written to from
 * {@see \CraftCms\Commerce\License\Elements\License::afterSave()}.
 */
class License extends BaseModel
{
    #[\Override]
    protected $table = Table::LICENSES;

    public $timestamps = false;

    /**
     * `id` is a foreign key to `elements.id`, not an auto-increment column.
     */
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'variantId' => 'integer',
        'orderId' => 'integer',
        'userId' => 'integer',
    ];
}
