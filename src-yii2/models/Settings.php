<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Settings} */
class_alias(\CraftCms\Commerce\Settings::class, 'craft\commerce\models\Settings');

/** @phpstan-ignore-next-line */
if (false) {
    class Settings extends \CraftCms\Commerce\Settings {}
}
