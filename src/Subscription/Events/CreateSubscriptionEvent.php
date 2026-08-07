<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\base\Plan;
use craft\commerce\models\subscriptions\SubscriptionForm;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Cms\User\Elements\User;

class CreateSubscriptionEvent
{
    use ValidatableEvent;

    public User $user;
    public Plan $plan;
    public SubscriptionForm $parameters;
}
