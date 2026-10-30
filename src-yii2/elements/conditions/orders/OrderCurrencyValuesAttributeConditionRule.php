<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\OrderCurrencyValuesAttributeConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\OrderCurrencyValuesAttributeConditionRule::class, 'craft\commerce\elements\conditions\orders\OrderCurrencyValuesAttributeConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderCurrencyValuesAttributeConditionRule extends \CraftCms\Commerce\Order\Conditions\OrderCurrencyValuesAttributeConditionRule {}
}
