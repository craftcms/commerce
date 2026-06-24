<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use DateTime;

class SubscriptionPaymentEvent
{
    public Subscription $subscription;
    public SubscriptionPayment $payment;
    public DateTime $paidUntil;
}
