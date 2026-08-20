<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ShippingMethodOrderCondition} */
class_alias(\CraftCms\Commerce\Order\Conditions\ShippingMethodOrderCondition::class, 'craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodOrderCondition extends \CraftCms\Commerce\Order\Conditions\ShippingMethodOrderCondition {}
}
