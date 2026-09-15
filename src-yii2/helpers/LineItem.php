<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\LineItem} */
class_alias(\CraftCms\Commerce\Helpers\LineItem::class, 'craft\commerce\helpers\LineItem');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItem extends \CraftCms\Commerce\Helpers\LineItem {}
}
