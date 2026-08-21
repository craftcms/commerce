<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingMethod} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingMethod::class, 'craft\commerce\models\ShippingMethod');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethod extends \CraftCms\Commerce\Shipping\Models\ShippingMethod {}
}
