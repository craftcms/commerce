<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Store\Data\Store;

/**
 * @deprecated 6.0.0
 */
class StoreEvent extends CancelableEvent
{
    /**
     * @var Store The store
     */
    public Store $store;

    /**
     * @var bool Whether the store is new
     */
    public bool $isNew = false;
}
