<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\OrderCondition} */
class_alias(\CraftCms\Commerce\Order\Conditions\OrderCondition::class, 'craft\commerce\elements\conditions\orders\OrderCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderCondition extends \CraftCms\Commerce\Order\Conditions\OrderCondition {}
}
