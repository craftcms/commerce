<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\LineItemException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\LineItemException::class, 'craft\commerce\errors\LineItemException');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItemException extends \CraftCms\Commerce\Order\Exceptions\LineItemException {}
}
