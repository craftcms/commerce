<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ShippingAddressZoneConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ShippingAddressZoneConditionRule::class, 'craft\commerce\elements\conditions\orders\ShippingAddressZoneConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingAddressZoneConditionRule extends \CraftCms\Commerce\Order\Conditions\ShippingAddressZoneConditionRule {}
}
