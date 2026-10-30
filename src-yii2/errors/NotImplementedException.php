<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Exceptions\NotImplementedException} */
class_alias(\CraftCms\Commerce\Exceptions\NotImplementedException::class, 'craft\commerce\errors\NotImplementedException');

/** @phpstan-ignore-next-line */
if (false) {
    class NotImplementedException extends \CraftCms\Commerce\Exceptions\NotImplementedException {}
}
