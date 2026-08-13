<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_plans` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Subscription\Plans} to read/write rows, which are then hydrated
 * into (or persisted from) the gateway-specific business {@see \craft\commerce\base\Plan}
 * object that the rest of the codebase actually works with.
 */
class Plan extends BaseModel
{
    #[\Override]
    protected $table = Table::PLANS;

    #[\Override]
    protected $casts = [
        'gatewayId' => 'integer',
        'planInformationId' => 'integer',
        'enabled' => 'boolean',
        'isArchived' => 'boolean',
        'dateArchived' => 'datetime',
        'sortOrder' => 'integer',
    ];
}
