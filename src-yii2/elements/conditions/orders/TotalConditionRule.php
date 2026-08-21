<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalConditionRule {}
}
