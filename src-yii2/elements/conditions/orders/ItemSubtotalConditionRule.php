<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ItemSubtotalConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ItemSubtotalConditionRule::class, 'craft\commerce\elements\conditions\orders\ItemSubtotalConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ItemSubtotalConditionRule extends \CraftCms\Commerce\Order\Conditions\ItemSubtotalConditionRule {}
}
