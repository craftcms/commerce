<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Exceptions\SubscriptionException} */
class_alias(\CraftCms\Commerce\Subscription\Exceptions\SubscriptionException::class, 'craft\commerce\errors\SubscriptionException');

/** @phpstan-ignore-next-line */
if (false) {
    class SubscriptionException extends \CraftCms\Commerce\Subscription\Exceptions\SubscriptionException {}
}
