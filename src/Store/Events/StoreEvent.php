<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Events;

use craft\commerce\models\Store;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class StoreEvent
{
    use ValidatableEvent;

    public function __construct(
        public Store $store,
        public bool $isNew = false,
    ) {}
}
