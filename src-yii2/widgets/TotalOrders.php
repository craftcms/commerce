<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\TotalOrders} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\TotalOrders::class, 'craft\commerce\widgets\TotalOrders');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalOrders extends \CraftCms\Commerce\Dashboard\Widgets\TotalOrders {}
}
