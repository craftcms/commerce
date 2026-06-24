<?php

namespace craft\commerce\models\responses;

/** @deprecated use {@see \CraftCms\Commerce\Subscription\Responses\DummySubscriptionResponse} */
class_alias(\CraftCms\Commerce\Subscription\Responses\DummySubscriptionResponse::class, 'craft\commerce\models\responses\DummySubscriptionResponse');

/** @phpstan-ignore-next-line */
if (false) {
    class DummySubscriptionResponse extends \CraftCms\Commerce\Subscription\Responses\DummySubscriptionResponse {}
}
