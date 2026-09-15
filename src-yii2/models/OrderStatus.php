<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Data\OrderStatus} */
class_alias(\CraftCms\Commerce\Order\Data\OrderStatus::class, 'craft\commerce\models\OrderStatus');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderStatus extends \CraftCms\Commerce\Order\Data\OrderStatus {}
}
