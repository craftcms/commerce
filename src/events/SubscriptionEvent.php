<?php

namespace craft\commerce\events;

use craft\commerce\elements\Subscription;
use craft\events\CancelableEvent;

/**
 * Class SubscriptionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class SubscriptionEvent extends CancelableEvent
{
    // Properties
    // ==========================================================================

    /**
     * @var Subscription Subscription
     */
    public $subscription;
}
