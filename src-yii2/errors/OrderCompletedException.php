<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\OrderCompletedException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\OrderCompletedException::class, 'craft\commerce\errors\OrderCompletedException');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderCompletedException extends \CraftCms\Commerce\Order\Exceptions\OrderCompletedException {}
}
