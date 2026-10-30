<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule::class, 'craft\commerce\elements\conditions\orders\ShippingMethodConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodConditionRule extends \CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule {}
}
