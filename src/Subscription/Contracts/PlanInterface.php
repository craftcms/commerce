<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Contracts;

interface PlanInterface
{
    public function canSwitchFrom(PlanInterface $currentPlan): bool;
}
