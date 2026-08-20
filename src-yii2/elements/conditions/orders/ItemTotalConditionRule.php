<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ItemTotalConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ItemTotalConditionRule::class, 'craft\commerce\elements\conditions\orders\ItemTotalConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ItemTotalConditionRule extends \CraftCms\Commerce\Order\Conditions\ItemTotalConditionRule {}
}
