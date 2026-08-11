<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exceptions\CurrencyException} */
class_alias(\CraftCms\Commerce\Order\Exceptions\CurrencyException::class, 'craft\commerce\errors\CurrencyException');

/** @phpstan-ignore-next-line */
if (false) {
    class CurrencyException extends \CraftCms\Commerce\Order\Exceptions\CurrencyException {}
}
