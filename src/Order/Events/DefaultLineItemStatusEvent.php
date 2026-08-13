<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\Models\LineItemStatus;

class DefaultLineItemStatusEvent
{
    public function __construct(
        public LineItem $lineItem,
        public ?LineItemStatus $lineItemStatus = null,
    ) {
    }
}
