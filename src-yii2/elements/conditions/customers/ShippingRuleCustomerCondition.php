<?php

namespace craft\commerce\elements\conditions\customers;

/** @deprecated use {@see \CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition} */
class_alias(\CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition::class, 'craft\commerce\elements\conditions\customers\ShippingRuleCustomerCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingRuleCustomerCondition extends \CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition {}
}
