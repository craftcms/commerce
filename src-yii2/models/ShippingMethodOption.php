<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingMethodOption} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingMethodOption::class, 'craft\commerce\models\ShippingMethodOption');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodOption extends \CraftCms\Commerce\Shipping\Models\ShippingMethodOption {}
}
