<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\LineItemAdding} instead.
 */
class AddLineItemEvent extends CancelableEvent
{
    /**
     * @var LineItem The line item model.
     */
    public LineItem $lineItem;

    /**
     * @var bool If this is a new line item.
     */
    public bool $isNew = false;
}
