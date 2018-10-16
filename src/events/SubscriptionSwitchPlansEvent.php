<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\Plan;
use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\events\CancelableEvent;

/**
 * Class SubscriptionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionSwitchPlansEvent extends CancelableEvent
{
    // Properties
    // ==========================================================================

    /**
     * @var Plan The plan user is switching from
     */
    public $oldPlan;

    /**
     * @var Subscription Subscription
     */
    public $subscription;

    /**
     * @var Plan The plan user is switching to
     */
    public $newPlan;

    /**
     * @var SwitchPlansForm parameters
     */
    public $parameters;
}
