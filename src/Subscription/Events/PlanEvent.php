<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\base\Plan;

class PlanEvent
{
    public function __construct(
        public Plan $plan,
    ) {}
}
