<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_paymentcurrencies` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Payment\PaymentCurrencies}
 * to read/write rows, which are then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Payment\Models\PaymentCurrency} object that the rest of the codebase
 * actually works with.
 */
class PaymentCurrency extends BaseModel
{
    #[\Override]
    protected $table = Table::PAYMENTCURRENCIES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'rate' => 'float',
    ];
}
