<?php

namespace craft\commerce\events;

use craft\db\Query;
use craft\events\CancelableEvent;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\InactiveCartsPurging} instead.
 */
class CartPurgeEvent extends CancelableEvent
{
    /**
     * @var Query The query that identifies the order IDs to be purged.
     */
    public Query $inactiveCartsQuery;
}
