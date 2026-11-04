<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class LineItemEvent extends Event
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
