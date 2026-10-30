<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Order\Actions\UpdateOrderStatus} */
class_alias(\CraftCms\Commerce\Order\Actions\UpdateOrderStatus::class, 'craft\commerce\elements\actions\UpdateOrderStatus');

/** @phpstan-ignore-next-line */
if (false) {
    class UpdateOrderStatus extends \CraftCms\Commerce\Order\Actions\UpdateOrderStatus {}
}
