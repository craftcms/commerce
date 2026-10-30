<?php

namespace craft\commerce\elements\conditions\customers;

/** @deprecated use {@see \CraftCms\Commerce\Customer\Conditions\ShippingMethodCustomerCondition} */
class_alias(\CraftCms\Commerce\Customer\Conditions\ShippingMethodCustomerCondition::class, 'craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodCustomerCondition extends \CraftCms\Commerce\Customer\Conditions\ShippingMethodCustomerCondition {}
}
