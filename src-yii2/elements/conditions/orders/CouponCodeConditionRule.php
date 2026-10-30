<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule::class, 'craft\commerce\elements\conditions\orders\CouponCodeConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class CouponCodeConditionRule extends \CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule {}
}
