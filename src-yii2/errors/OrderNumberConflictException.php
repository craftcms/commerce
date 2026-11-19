<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\OrderNumberConflictException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\OrderNumberConflictException::class, 'craft\commerce\errors\OrderNumberConflictException');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderNumberConflictException extends \CraftCms\Commerce\Order\Exceptions\OrderNumberConflictException {}
}
