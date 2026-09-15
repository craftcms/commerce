<?php

namespace craft\commerce\elements\conditions\customers;

/** @deprecated use {@see \CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition} */
class_alias(\CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition::class, 'craft\commerce\elements\conditions\customers\DiscountCustomerCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class DiscountCustomerCondition extends \CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition {}
}
