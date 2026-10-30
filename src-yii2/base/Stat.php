<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Stats\Stat} */
class_alias(\CraftCms\Commerce\Stats\Stat::class, 'craft\commerce\base\Stat');

/** @phpstan-ignore-next-line */
if (false) {
    class Stat extends \CraftCms\Commerce\Stats\Stat {}
}
