<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingAddressZone} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingAddressZone::class, 'craft\commerce\models\ShippingAddressZone');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingAddressZone extends \CraftCms\Commerce\Shipping\Models\ShippingAddressZone {}
}
