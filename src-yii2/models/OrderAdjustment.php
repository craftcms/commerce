<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Data\OrderAdjustment} */
class_alias(\CraftCms\Commerce\Order\Data\OrderAdjustment::class, 'craft\commerce\models\OrderAdjustment');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderAdjustment extends \CraftCms\Commerce\Order\Data\OrderAdjustment {}
}
