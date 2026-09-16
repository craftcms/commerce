<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\TotalRevenue} */
class_alias(\CraftCms\Commerce\Stats\TotalRevenue::class, 'craft\commerce\stats\TotalRevenue');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalRevenue extends \CraftCms\Commerce\Stats\TotalRevenue {}
}
