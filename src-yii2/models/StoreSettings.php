<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Store\Models\StoreSettings} */
class_alias(\CraftCms\Commerce\Store\Models\StoreSettings::class, 'craft\commerce\models\StoreSettings');

/** @phpstan-ignore-next-line */
if (false) {
    class StoreSettings extends \CraftCms\Commerce\Store\Models\StoreSettings {}
}
