<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Order\Elements\Order} */
class_alias(\CraftCms\Commerce\Order\Elements\Order::class, 'craft\commerce\elements\Order');

/** @phpstan-ignore-next-line */
if (false) {
    class Order extends \CraftCms\Commerce\Order\Elements\Order {}
}
