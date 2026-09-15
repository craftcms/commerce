<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\OrderValuesAttributeConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\OrderValuesAttributeConditionRule::class, 'craft\commerce\elements\conditions\orders\OrderValuesAttributeConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderValuesAttributeConditionRule extends \CraftCms\Commerce\Order\Conditions\OrderValuesAttributeConditionRule {}
}
