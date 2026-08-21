<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\LineItem\Data\LineItem;

class LineItemEvent
{
    public function __construct(
        public LineItem $lineItem,
        public bool $isNew = false,
    ) {
    }
}
