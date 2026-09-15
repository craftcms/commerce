<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;

class ModifyCartInfoEvent
{
    public function __construct(
        public array $cartInfo = [],
        public ?Order $cart = null,
    ) {
    }
}
