<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\LineItemNotFoundException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\LineItemNotFoundException::class, 'craft\commerce\errors\LineItemNotFoundException');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItemNotFoundException extends \CraftCms\Commerce\Order\Exceptions\LineItemNotFoundException {}
}
