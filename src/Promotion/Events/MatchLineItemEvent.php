<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class MatchLineItemEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public Discount $discount;
}
