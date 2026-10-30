<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\NewCustomers} */
class_alias(\CraftCms\Commerce\Stats\NewCustomers::class, 'craft\commerce\stats\NewCustomers');

/** @phpstan-ignore-next-line */
if (false) {
    class NewCustomers extends \CraftCms\Commerce\Stats\NewCustomers {}
}
