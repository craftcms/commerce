<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\OrderSiteConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\OrderSiteConditionRule::class, 'craft\commerce\elements\conditions\orders\OrderSiteConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderSiteConditionRule extends \CraftCms\Commerce\Order\Conditions\OrderSiteConditionRule {}
}
