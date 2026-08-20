<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition} */
class_alias(\CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition::class, 'craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingRuleOrderCondition extends \CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition {}
}
