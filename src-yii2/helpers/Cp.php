<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Cp} */
class_alias(\CraftCms\Commerce\Helpers\Cp::class, 'craft\commerce\helpers\Cp');

/** @phpstan-ignore-next-line */
if (false) {
    class Cp extends \CraftCms\Commerce\Helpers\Cp {}
}
