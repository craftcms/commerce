<?php

namespace craft\commerce\adjusters;

/** @deprecated use {@see \CraftCms\Commerce\Order\Adjuster\Shipping} */
class_alias(\CraftCms\Commerce\Order\Adjuster\Shipping::class, 'craft\commerce\adjusters\Shipping');

/** @phpstan-ignore-next-line */
if (false) {
    class Shipping extends \CraftCms\Commerce\Order\Adjuster\Shipping {}
}
