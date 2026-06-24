<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Store\Models\Store} */
class_alias(\CraftCms\Commerce\Store\Models\Store::class, 'craft\commerce\models\Store');

/** @phpstan-ignore-next-line */
if (false) {
    class Store extends \CraftCms\Commerce\Store\Models\Store {}
}
