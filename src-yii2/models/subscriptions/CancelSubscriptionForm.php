<?php

namespace craft\commerce\models\subscriptions;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Forms\CancelSubscriptionForm} */
class_alias(\CraftCms\Commerce\Subscription\Forms\CancelSubscriptionForm::class, 'craft\commerce\models\subscriptions\CancelSubscriptionForm');

/** @phpstan-ignore-next-line */
if (false) {
    class CancelSubscriptionForm extends \CraftCms\Commerce\Subscription\Forms\CancelSubscriptionForm {}
}
