<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\TopProducts} */
class_alias(\CraftCms\Commerce\Stats\TopProducts::class, 'craft\commerce\stats\TopProducts');

/** @phpstan-ignore-next-line */
if (false) {
    class TopProducts extends \CraftCms\Commerce\Stats\TopProducts {}
}
