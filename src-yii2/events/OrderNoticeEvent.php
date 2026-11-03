<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Order\Data\OrderNotice;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Order\Events\OrderNoticeSaving} instead.
 */
class OrderNoticeEvent extends CancelableEvent
{
    /**
     * @var OrderNotice The order notice model.
     */
    public $orderNotice;
}
