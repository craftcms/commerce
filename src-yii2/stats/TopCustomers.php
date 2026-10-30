<?php

namespace craft\commerce\stats;

/** @deprecated use {@see \CraftCms\Commerce\Stats\TopCustomers} */
class_alias(\CraftCms\Commerce\Stats\TopCustomers::class, 'craft\commerce\stats\TopCustomers');

/** @phpstan-ignore-next-line */
if (false) {
    class TopCustomers extends \CraftCms\Commerce\Stats\TopCustomers {}
}
