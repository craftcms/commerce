<?php

namespace craft\commerce\elements\conditions\customers;

/** @deprecated use {@see \CraftCms\Commerce\Customer\Conditions\SignedInConditionRule} */
class_alias(\CraftCms\Commerce\Customer\Conditions\SignedInConditionRule::class, 'craft\commerce\elements\conditions\customers\SignedInConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class SignedInConditionRule extends \CraftCms\Commerce\Customer\Conditions\SignedInConditionRule {}
}
