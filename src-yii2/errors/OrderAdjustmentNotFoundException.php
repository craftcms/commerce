<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException::class, 'craft\commerce\errors\OrderAdjustmentNotFoundException');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderAdjustmentNotFoundException extends \CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException {}
}
