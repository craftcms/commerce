<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Data\ShippingRule} */
class_alias(\CraftCms\Commerce\Shipping\Data\ShippingRule::class, 'craft\commerce\models\ShippingRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingRule extends \CraftCms\Commerce\Shipping\Data\ShippingRule {}
}
