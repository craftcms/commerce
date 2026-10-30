<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\DiscountedItemSubtotalConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\DiscountedItemSubtotalConditionRule::class, 'craft\commerce\elements\conditions\orders\DiscountedItemSubtotalConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class DiscountedItemSubtotalConditionRule extends \CraftCms\Commerce\Order\Conditions\DiscountedItemSubtotalConditionRule {}
}
