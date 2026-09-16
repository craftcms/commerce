<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Order} */
class_alias(\CraftCms\Commerce\Helpers\Order::class, 'craft\commerce\helpers\Order');

/** @phpstan-ignore-next-line */
if (false) {
    class Order extends \CraftCms\Commerce\Helpers\Order {}
}
