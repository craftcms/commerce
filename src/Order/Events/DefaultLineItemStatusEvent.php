<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use yii\base\Event;

class DefaultLineItemStatusEvent extends Event
{
    public function __construct(
        public LineItem $lineItem,
        public ?LineItemStatus $lineItemStatus = null,
    ) {
    }
}
