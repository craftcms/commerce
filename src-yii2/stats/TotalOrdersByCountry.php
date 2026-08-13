<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\TotalOrdersByCountry} */
class_alias(\CraftCms\Commerce\Stats\TotalOrdersByCountry::class, 'craft\commerce\stats\TotalOrdersByCountry');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalOrdersByCountry extends \CraftCms\Commerce\Stats\TotalOrdersByCountry {}
}
