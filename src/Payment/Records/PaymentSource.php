<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_paymentsources` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Payment\PaymentSources} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Payment\Models\PaymentSource}
 * object that the rest of the codebase actually works with.
 */
class PaymentSource extends BaseModel
{
    #[\Override]
    protected $table = Table::PAYMENTSOURCES;

    #[\Override]
    protected $casts = [
        'gatewayId' => 'integer',
        'customerId' => 'integer',
    ];
}
