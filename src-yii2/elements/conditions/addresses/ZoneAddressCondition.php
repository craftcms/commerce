<?php

namespace craft\commerce\elements\conditions\addresses;

/** @deprecated use {@see \CraftCms\Commerce\Address\Conditions\ZoneAddressCondition} */
class_alias(\CraftCms\Commerce\Address\Conditions\ZoneAddressCondition::class, 'craft\commerce\elements\conditions\addresses\ZoneAddressCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ZoneAddressCondition extends \CraftCms\Commerce\Address\Conditions\ZoneAddressCondition {}
}
