<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalPriceConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalPriceConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule {}
}
