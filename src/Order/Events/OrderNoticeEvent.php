<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Data\OrderNotice;

class OrderNoticeEvent
{
    use ValidatableEvent;

    public function __construct(
        public OrderNotice $orderNotice,
    ) {
    }
}
