<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\TotalTaxConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\TotalTaxConditionRule::class, 'craft\commerce\elements\conditions\orders\TotalTaxConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalTaxConditionRule extends \CraftCms\Commerce\Order\Conditions\TotalTaxConditionRule {}
}
