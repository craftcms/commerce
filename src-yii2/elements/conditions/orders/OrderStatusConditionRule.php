<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule::class, 'craft\commerce\elements\conditions\orders\OrderStatusConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderStatusConditionRule extends \CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule {}
}
