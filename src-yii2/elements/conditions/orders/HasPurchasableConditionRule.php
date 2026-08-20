<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule::class, 'craft\commerce\elements\conditions\orders\HasPurchasableConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class HasPurchasableConditionRule extends \CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule {}
}
