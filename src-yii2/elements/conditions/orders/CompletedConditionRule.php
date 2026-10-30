<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\CompletedConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\CompletedConditionRule::class, 'craft\commerce\elements\conditions\orders\CompletedConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class CompletedConditionRule extends \CraftCms\Commerce\Order\Conditions\CompletedConditionRule {}
}
