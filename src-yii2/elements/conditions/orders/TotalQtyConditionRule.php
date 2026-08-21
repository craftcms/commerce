<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalQtyConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalQtyConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule {}
}
