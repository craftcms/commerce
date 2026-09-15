<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Localization} */
class_alias(\CraftCms\Commerce\Helpers\Localization::class, 'craft\commerce\helpers\Localization');

/** @phpstan-ignore-next-line */
if (false) {
    class Localization extends \CraftCms\Commerce\Helpers\Localization {}
}
