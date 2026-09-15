<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use craft\db\Query;

class ModifyPurchasablesTableQueryEvent
{
    public function __construct(
        public Query $query,
        public ?string $search = null,
    ) {
    }
}
