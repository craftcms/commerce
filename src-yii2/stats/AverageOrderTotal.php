<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\AverageOrderTotal} */
class_alias(\CraftCms\Commerce\Stats\AverageOrderTotal::class, 'craft\commerce\stats\AverageOrderTotal');

/** @phpstan-ignore-next-line */
if (false) {
    class AverageOrderTotal extends \CraftCms\Commerce\Stats\AverageOrderTotal {}
}
