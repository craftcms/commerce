<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

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
