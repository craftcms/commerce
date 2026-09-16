<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\TotalRevenue} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\TotalRevenue::class, 'craft\commerce\widgets\TotalRevenue');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalRevenue extends \CraftCms\Commerce\Dashboard\Widgets\TotalRevenue {}
}
