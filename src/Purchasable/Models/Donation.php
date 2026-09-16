<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

class Donation extends BaseModel
{
    #[\Override]
    protected $table = Table::DONATIONS;

    public $timestamps = false;

    /**
     * `id` is a foreign key to `elements.id`, not an auto-increment column — without this,
     * Eloquent overwrites it with `lastInsertId()` (0, for a non-auto-increment PK) after insert.
     */
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'dateCreated' => 'datetime',
        'dateUpdated' => 'datetime',
    ];
}
