<?php

namespace craft\commerce\models\subscriptions;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Models\DummyPlan} */
class_alias(\CraftCms\Commerce\Subscription\Models\DummyPlan::class, 'craft\commerce\models\subscriptions\DummyPlan');

/** @phpstan-ignore-next-line */
if (false) {
    class DummyPlan extends \CraftCms\Commerce\Subscription\Models\DummyPlan {}
}
