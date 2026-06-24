<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\models\OrderNotice;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class OrderNoticeEvent
{
    use ValidatableEvent;

    public OrderNotice $orderNotice;
}
