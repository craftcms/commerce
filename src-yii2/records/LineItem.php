<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Order\LineItem\Models\LineItem} */
class_alias(\CraftCms\Commerce\Order\LineItem\Models\LineItem::class, 'craft\commerce\records\LineItem');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItem extends \CraftCms\Commerce\Order\LineItem\Models\LineItem {}
}
