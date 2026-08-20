<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule::class, 'craft\commerce\elements\conditions\orders\ContainsPurchasablesConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ContainsPurchasablesConditionRule extends \CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule {}
}
