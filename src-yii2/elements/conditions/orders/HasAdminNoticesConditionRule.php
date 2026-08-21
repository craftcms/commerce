<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\HasAdminNoticesConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\HasAdminNoticesConditionRule::class, 'craft\commerce\elements\conditions\orders\HasAdminNoticesConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class HasAdminNoticesConditionRule extends \CraftCms\Commerce\Order\Conditions\HasAdminNoticesConditionRule {}
}
