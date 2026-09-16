<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\CustomerConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\CustomerConditionRule::class, 'craft\commerce\elements\conditions\orders\CustomerConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class CustomerConditionRule extends \CraftCms\Commerce\Order\Conditions\CustomerConditionRule {}
}
