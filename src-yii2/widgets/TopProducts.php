<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\TopProducts} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\TopProducts::class, 'craft\commerce\widgets\TopProducts');

/** @phpstan-ignore-next-line */
if (false) {
    class TopProducts extends \CraftCms\Commerce\Dashboard\Widgets\TopProducts {}
}
