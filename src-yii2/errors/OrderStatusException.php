<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\OrderStatusException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\OrderStatusException::class, 'craft\commerce\errors\OrderStatusException');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderStatusException extends \CraftCms\Commerce\Order\Exceptions\OrderStatusException {}
}
