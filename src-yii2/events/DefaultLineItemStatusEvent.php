<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\DefaultLineItemStatusResolving} instead.
 */
class DefaultLineItemStatusEvent extends Event
{
    /**
     * @var LineItemStatus|null The default line item status based on the line item
     */
    public ?LineItemStatus $lineItemStatus = null;

    /**
     * @var LineItem The line item used to determine the line item status.
     */
    public LineItem $lineItem;
}
