<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\base\Plan;
use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class SubscriptionSwitchPlansEvent
{
    use ValidatableEvent;

    public function __construct(
        public Plan $oldPlan,
        public Subscription $subscription,
        public Plan $newPlan,
        public SwitchPlansForm $parameters,
    ) {}
}
