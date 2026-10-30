<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Locale} */
class_alias(\CraftCms\Commerce\Helpers\Locale::class, 'craft\commerce\helpers\Locale');

/** @phpstan-ignore-next-line */
if (false) {
    class Locale extends \CraftCms\Commerce\Helpers\Locale {}
}
