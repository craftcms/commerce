<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Models\LineItemStatus} */
class_alias(\CraftCms\Commerce\Order\Models\LineItemStatus::class, 'craft\commerce\models\LineItemStatus');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItemStatus extends \CraftCms\Commerce\Order\Models\LineItemStatus {}
}
