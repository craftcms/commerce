<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\models\LineItem;
use CraftCms\Commerce\Order\Models\LineItemStatus;

class DefaultLineItemStatusEvent
{
    public ?LineItemStatus $lineItemStatus = null;
    public LineItem $lineItem;
}
