<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Models\OrderHistory} */
class_alias(\CraftCms\Commerce\Order\Models\OrderHistory::class, 'craft\commerce\models\OrderHistory');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderHistory extends \CraftCms\Commerce\Order\Models\OrderHistory {}
}
