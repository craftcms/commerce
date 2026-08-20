<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalPaidConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalPaidConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalPaidConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalPaidConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalPaidConditionRule {}
}
