<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\events\CancelableEvent;


class ProcessPaymentEvent extends CancelableEvent
{
    // Properties
    // =============================================================================

    /**
     * @var Order Order
     */
    public $order;
}
