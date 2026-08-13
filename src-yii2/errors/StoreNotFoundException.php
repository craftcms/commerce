<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Store\Exceptions\StoreNotFoundException} */
class_alias(\CraftCms\Commerce\Store\Exceptions\StoreNotFoundException::class, 'craft\commerce\errors\StoreNotFoundException');

/** @phpstan-ignore-next-line */
if (false) {
    class StoreNotFoundException extends \CraftCms\Commerce\Store\Exceptions\StoreNotFoundException {}
}
