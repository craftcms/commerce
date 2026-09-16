<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalWeightConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalWeightConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalWeightConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalWeightConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalWeightConditionRule {}
}
