<?php

namespace craft\commerce\elements\conditions\customers;

/** @deprecated use {@see \CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule} */
class_alias(\CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule::class, 'craft\commerce\elements\conditions\customers\HasOrdersConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class HasOrdersConditionRule extends \CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule {}
}
