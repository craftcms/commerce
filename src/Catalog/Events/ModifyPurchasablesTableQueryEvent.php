<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\db\Query;

class ModifyPurchasablesTableQueryEvent
{
    public Query $query;
    public ?string $search = null;
}
