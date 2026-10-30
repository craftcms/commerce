<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Store\Data\Store;
use yii\base\Event;

class StoreEvent extends Event
{
    use ValidatableEvent;

    public function __construct(
        public Store $store,
        public bool $isNew = false,
    ) {
    }
}
