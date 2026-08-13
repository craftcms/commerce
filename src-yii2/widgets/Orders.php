<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\Orders} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\Orders::class, 'craft\commerce\widgets\Orders');

/** @phpstan-ignore-next-line */
if (false) {
    class Orders extends \CraftCms\Commerce\Dashboard\Widgets\Orders {}
}
