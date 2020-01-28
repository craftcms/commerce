<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\Plan;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\elements\User;
use craft\events\CancelableEvent;

/**
 * Class CreateSubscriptionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CreateSubscriptionEvent extends CancelableEvent
{
    /**
     * @var User The subscribing user
     */
    public $user;

    /**
     * @var Plan The subscription plan
     */
    public $plan;

    /**
     * @var SubscriptionForm Additional parameters
     */
    public $parameters;
}
