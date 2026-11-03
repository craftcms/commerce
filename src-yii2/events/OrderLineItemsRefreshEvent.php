<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class OrderLineItemsRefreshEvent extends Event
{
    /**
     * @var LineItem[]
     */
    public array $lineItems;

    public bool $recalculate = false;
}
