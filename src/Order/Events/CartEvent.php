<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\models\LineItem;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Elements\Order;

class CartEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public Order $order;
}
