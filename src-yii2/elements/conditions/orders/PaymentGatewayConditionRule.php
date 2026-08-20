<?php

namespace craft\commerce\elements\conditions\orders;

/** @deprecated use {@see \CraftCms\Commerce\Order\Conditions\PaymentGatewayConditionRule} */
class_alias(\CraftCms\Commerce\Order\Conditions\PaymentGatewayConditionRule::class, 'craft\commerce\elements\conditions\orders\PaymentGatewayConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentGatewayConditionRule extends \CraftCms\Commerce\Order\Conditions\PaymentGatewayConditionRule {}
}
