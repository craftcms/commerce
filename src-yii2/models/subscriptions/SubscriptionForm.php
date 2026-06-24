<?php

namespace craft\commerce\models\subscriptions;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Forms\SubscriptionForm} */
class_alias(\CraftCms\Commerce\Subscription\Forms\SubscriptionForm::class, 'craft\commerce\models\subscriptions\SubscriptionForm');

/** @phpstan-ignore-next-line */
if (false) {
    class SubscriptionForm extends \CraftCms\Commerce\Subscription\Forms\SubscriptionForm {}
}
