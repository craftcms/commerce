<?php

namespace craft\commerce\elements\conditions\addresses;

/** @deprecated use {@see \CraftCms\Commerce\Address\Conditions\GatewayAddressCondition} */
class_alias(\CraftCms\Commerce\Address\Conditions\GatewayAddressCondition::class, 'craft\commerce\elements\conditions\addresses\GatewayAddressCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class GatewayAddressCondition extends \CraftCms\Commerce\Address\Conditions\GatewayAddressCondition {}
}
