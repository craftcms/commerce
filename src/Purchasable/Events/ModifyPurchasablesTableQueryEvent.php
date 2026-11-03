<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use craft\db\Query;

// TODO: switch $query to an Illuminate query builder when OrdersController's purchasables table is migrated with the order edit page
class ModifyPurchasablesTableQueryEvent
{
    public function __construct(
        public Query $query,
        public ?string $search = null,
    ) {
    }
}
