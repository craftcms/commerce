<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class CartEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public Order $order;
}
