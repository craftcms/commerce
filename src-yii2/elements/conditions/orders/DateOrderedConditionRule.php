<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\DateOrderedConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\DateOrderedConditionRule::class, 'craft\commerce\elements\conditions\orders\DateOrderedConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class DateOrderedConditionRule extends \CraftCms\Commerce\Order\Conditions\DateOrderedConditionRule {}
}
