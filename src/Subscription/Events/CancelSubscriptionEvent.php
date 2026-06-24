<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class CancelSubscriptionEvent
{
    use ValidatableEvent;

    public Subscription $subscription;
    public CancelSubscriptionForm $parameters;
}
