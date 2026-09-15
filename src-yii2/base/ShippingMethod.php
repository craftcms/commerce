<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Data\BaseShippingMethod} */
class_alias(\CraftCms\Commerce\Shipping\Data\BaseShippingMethod::class, 'craft\commerce\base\ShippingMethod');

/** @phpstan-ignore-next-line */
if (false) {
    abstract class ShippingMethod extends \CraftCms\Commerce\Shipping\Data\BaseShippingMethod {}
}
