<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingRule} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingRule::class, 'craft\commerce\models\ShippingRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingRule extends \CraftCms\Commerce\Shipping\Models\ShippingRule {}
}
