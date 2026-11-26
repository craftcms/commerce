<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use Illuminate\Database\Query\Builder;

class ModifyPurchasablesTableQueryEvent
{
    public function __construct(
        public Builder $query,
        public ?string $search = null,
    ) {
    }
}
