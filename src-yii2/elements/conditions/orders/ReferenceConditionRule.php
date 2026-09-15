<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\ReferenceConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\ReferenceConditionRule::class, 'craft\commerce\elements\conditions\orders\ReferenceConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ReferenceConditionRule extends \CraftCms\Commerce\Order\Conditions\ReferenceConditionRule {}
}
