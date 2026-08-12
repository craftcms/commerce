<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\db\Query;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class CartPurgeEvent
{
    use ValidatableEvent;

    public function __construct(
        public Query $inactiveCartsQuery,
    ) {}
}
