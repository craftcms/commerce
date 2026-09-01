<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_customers` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Customer\Customers} to read/write rows.
 */
class Customer extends BaseModel
{
    #[\Override]
    protected $table = Table::CUSTOMERS;

    #[\Override]
    protected $casts = [
        'customerId' => 'integer',
        'primaryBillingAddressId' => 'integer',
        'primaryShippingAddressId' => 'integer',
        'primaryPaymentSourceId' => 'integer',
    ];
}
