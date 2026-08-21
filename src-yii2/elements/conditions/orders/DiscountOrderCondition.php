<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\DiscountOrderCondition} */
class_alias(\CraftCms\Commerce\Order\Conditions\DiscountOrderCondition::class, 'craft\commerce\elements\conditions\orders\DiscountOrderCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class DiscountOrderCondition extends \CraftCms\Commerce\Order\Conditions\DiscountOrderCondition {}
}
