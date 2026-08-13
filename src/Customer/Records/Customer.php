<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_customers` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Customer\Customers} to read/write rows.
 *
 * The legacy `craft\commerce\records\Customer` stays alongside this class — it's still `use`d
 * directly by the still-legacy `src-yii2\behaviors\CustomerBehavior`, which is attached to every
 * `User` element — this class only replaces `src/`'s own usage.
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
