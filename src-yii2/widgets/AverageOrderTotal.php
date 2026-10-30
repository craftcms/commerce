<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal::class, 'craft\commerce\widgets\AverageOrderTotal');

/** @phpstan-ignore-next-line */
if (false) {
    class AverageOrderTotal extends \CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal {}
}
