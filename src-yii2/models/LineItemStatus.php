<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Data\LineItemStatus} */
class_alias(\CraftCms\Commerce\Order\Data\LineItemStatus::class, 'craft\commerce\models\LineItemStatus');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItemStatus extends \CraftCms\Commerce\Order\Data\LineItemStatus {}
}
