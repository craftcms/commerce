<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Order\Data\OrderNotice} */
class_alias(\CraftCms\Commerce\Order\Data\OrderNotice::class, 'craft\commerce\models\OrderNotice');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderNotice extends \CraftCms\Commerce\Order\Data\OrderNotice {}
}
