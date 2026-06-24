<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingCategory} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingCategory::class, 'craft\commerce\models\ShippingCategory');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingCategory extends \CraftCms\Commerce\Shipping\Models\ShippingCategory {}
}
