<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Data\ShippingMethod} */
class_alias(\CraftCms\Commerce\Shipping\Data\ShippingMethod::class, 'craft\commerce\models\ShippingMethod');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethod extends \CraftCms\Commerce\Shipping\Data\ShippingMethod {}
}
