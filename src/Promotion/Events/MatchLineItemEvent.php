<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\models\Discount;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

class MatchLineItemEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public Discount $discount;
}
