<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalDiscountConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalDiscountConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule {}
}
