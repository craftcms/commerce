<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_gateways` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Payment\Gateway\Gateways} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \craft\commerce\base\Gateway} object that the rest
 * of the codebase actually works with.
 */
class Gateway extends BaseModel
{
    #[\Override]
    protected $table = Table::GATEWAYS;

    #[\Override]
    protected $casts = [
        'isArchived' => 'boolean',
        'sortOrder' => 'integer',
        'settings' => 'array',
        'orderCondition' => 'array',
        'billingAddressCondition' => 'array',
        'shippingAddressCondition' => 'array',
        'dateArchived' => 'datetime',
    ];
}
