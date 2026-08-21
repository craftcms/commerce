<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\GatewayOrderCondition} */
class_alias(\CraftCms\Commerce\Order\Conditions\GatewayOrderCondition::class, 'craft\commerce\elements\conditions\orders\GatewayOrderCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class GatewayOrderCondition extends \CraftCms\Commerce\Order\Conditions\GatewayOrderCondition {}
}
