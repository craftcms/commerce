<?php

namespace craft\commerce\events;

use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use DateTime;
use yii\base\Event;

/**
 * Class SubscriptionPaymentEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionPaymentEvent extends Event
{
    // Properties
    // ==========================================================================

    /**
     * @var Subscription Subscription
     */
    public $subscription;

    /**
     * @var SubscriptionPayment Subscription payment
     */
    public $payment;

    /**
     * @var DateTime Date subscription paid until
     */
    public $paidUntil;
}
