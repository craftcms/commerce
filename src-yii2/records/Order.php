<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Order\Models\Order} */
class_alias(\CraftCms\Commerce\Order\Models\Order::class, 'craft\commerce\records\Order');

/** @phpstan-ignore-next-line */
if (false) {
    class Order extends \CraftCms\Commerce\Order\Models\Order {}
}
