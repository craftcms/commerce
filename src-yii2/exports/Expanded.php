<?php

namespace craft\commerce\exports;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exporters\Expanded} */
class_alias(\CraftCms\Commerce\Order\Exporters\Expanded::class, 'craft\commerce\exports\Expanded');

/** @phpstan-ignore-next-line */
if (false) {
    class Expanded extends \CraftCms\Commerce\Order\Exporters\Expanded {}
}
