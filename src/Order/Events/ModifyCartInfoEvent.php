<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\elements\Order;

class ModifyCartInfoEvent
{
    public array $cartInfo = [];
    public ?Order $cart = null;
}
