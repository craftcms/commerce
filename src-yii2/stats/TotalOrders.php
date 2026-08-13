<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\TotalOrders} */
class_alias(\CraftCms\Commerce\Stats\TotalOrders::class, 'craft\commerce\stats\TotalOrders');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalOrders extends \CraftCms\Commerce\Stats\TotalOrders {}
}
