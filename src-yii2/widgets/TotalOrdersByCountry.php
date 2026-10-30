<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry::class, 'craft\commerce\widgets\TotalOrdersByCountry');

/** @phpstan-ignore-next-line */
if (false) {
    class TotalOrdersByCountry extends \CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry {}
}
