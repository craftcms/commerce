<?php

namespace craft\commerce\elements\conditions\addresses;

/** @deprecated use {@see \CraftCms\Commerce\Address\Conditions\DiscountAddressCondition} */
class_alias(\CraftCms\Commerce\Address\Conditions\DiscountAddressCondition::class, 'craft\commerce\elements\conditions\addresses\DiscountAddressCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class DiscountAddressCondition extends \CraftCms\Commerce\Address\Conditions\DiscountAddressCondition {}
}
