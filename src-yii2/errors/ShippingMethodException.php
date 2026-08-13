<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Exceptions\ShippingMethodException} */
class_alias(\CraftCms\Commerce\Shipping\Exceptions\ShippingMethodException::class, 'craft\commerce\errors\ShippingMethodException');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingMethodException extends \CraftCms\Commerce\Shipping\Exceptions\ShippingMethodException {}
}
