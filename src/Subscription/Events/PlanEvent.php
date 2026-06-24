<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Events;

use craft\commerce\models\Plan;

class PlanEvent
{
    public Plan $plan;
}
