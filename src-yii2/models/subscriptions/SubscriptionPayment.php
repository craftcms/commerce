<?php

namespace craft\commerce\models\subscriptions;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Models\SubscriptionPayment} */
class_alias(\CraftCms\Commerce\Subscription\Models\SubscriptionPayment::class, 'craft\commerce\models\subscriptions\SubscriptionPayment');

/** @phpstan-ignore-next-line */
if (false) {
    class SubscriptionPayment extends \CraftCms\Commerce\Subscription\Models\SubscriptionPayment {}
}
