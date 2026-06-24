<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Models;

use craft\commerce\base\Plan;
use CraftCms\Commerce\Subscription\Contracts\PlanInterface;

class DummyPlan extends Plan
{
    // TODO: rename `$currentPlant` → `$currentPlan` in next major release.
    public function canSwitchFrom(PlanInterface $currentPlant): bool
    {
        return true;
    }
}
