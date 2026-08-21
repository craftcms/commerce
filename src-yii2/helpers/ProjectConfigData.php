<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\ProjectConfigData} */
class_alias(\CraftCms\Commerce\Helpers\ProjectConfigData::class, 'craft\commerce\helpers\ProjectConfigData');

/** @phpstan-ignore-next-line */
if (false) {
    class ProjectConfigData extends \CraftCms\Commerce\Helpers\ProjectConfigData {}
}
