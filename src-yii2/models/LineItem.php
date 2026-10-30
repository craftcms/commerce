<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\LineItem\Data\LineItem} */
class_alias(\CraftCms\Commerce\Order\LineItem\Data\LineItem::class, 'craft\commerce\models\LineItem');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItem extends \CraftCms\Commerce\Order\LineItem\Data\LineItem {}
}
