<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Data\ShippingMethodOption} */
class_alias(\CraftCms\Commerce\Shipping\Data\ShippingMethodOption::class, 'craft\commerce\models\ShippingMethodOption');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodOption extends \CraftCms\Commerce\Shipping\Data\ShippingMethodOption {}
}
