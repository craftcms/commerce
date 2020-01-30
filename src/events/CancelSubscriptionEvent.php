<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
    /**
     * @var Subscription Subscription
     */
    public $subscription;

    /**
     * @var CancelSubscriptionForm parameters
     */
    public $parameters;
}
