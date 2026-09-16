<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Data\ShippingAddressZone} */
class_alias(\CraftCms\Commerce\Shipping\Data\ShippingAddressZone::class, 'craft\commerce\models\ShippingAddressZone');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingAddressZone extends \CraftCms\Commerce\Shipping\Data\ShippingAddressZone {}
}
