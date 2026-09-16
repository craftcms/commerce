<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\PaidConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\PaidConditionRule::class, 'craft\commerce\elements\conditions\orders\PaidConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class PaidConditionRule extends \CraftCms\Commerce\Order\Conditions\PaidConditionRule {}
}
