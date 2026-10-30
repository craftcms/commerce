<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Email\Exceptions\EmailException} */
class_alias(\CraftCms\Commerce\Email\Exceptions\EmailException::class, 'craft\commerce\errors\EmailException');

/** @phpstan-ignore-next-line */
if (false) {
    class EmailException extends \CraftCms\Commerce\Email\Exceptions\EmailException {}
}
