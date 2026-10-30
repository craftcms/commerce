<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Data\OrderHistory} */
class_alias(\CraftCms\Commerce\Order\Data\OrderHistory::class, 'craft\commerce\models\OrderHistory');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderHistory extends \CraftCms\Commerce\Order\Data\OrderHistory {}
}
