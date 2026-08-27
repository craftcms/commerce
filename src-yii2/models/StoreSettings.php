<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Store\Data\StoreSettings} */
class_alias(\CraftCms\Commerce\Store\Data\StoreSettings::class, 'craft\commerce\models\StoreSettings');

/** @phpstan-ignore-next-line */
if (false) {
    class StoreSettings extends \CraftCms\Commerce\Store\Data\StoreSettings {}
}
