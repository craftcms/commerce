<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Exceptions\TransactionException} */
class_alias(\CraftCms\Commerce\Payment\Exceptions\TransactionException::class, 'craft\commerce\errors\TransactionException');

/** @phpstan-ignore-next-line */
if (false) {
    class TransactionException extends \CraftCms\Commerce\Payment\Exceptions\TransactionException {}
}
