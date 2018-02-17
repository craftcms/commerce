<?php

namespace craft\commerce\events;

use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\events\CancelableEvent;

/**
 * Class CancelSubscriptionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CancelSubscriptionEvent extends CancelableEvent
{
    // Properties
    // ==========================================================================

    /**
     * @var Subscription Subscription
     */
    public $subscription;

    /**
     * @var CancelSubscriptionForm parameters
     */
    public $parameters;
}
