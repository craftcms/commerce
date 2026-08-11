<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Models\OrderNotice;

class OrderNoticeEvent
{
    use ValidatableEvent;

    public OrderNotice $orderNotice;
}
