<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\TopCustomers} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\TopCustomers::class, 'craft\commerce\widgets\TopCustomers');

/** @phpstan-ignore-next-line */
if (false) {
    class TopCustomers extends \CraftCms\Commerce\Dashboard\Widgets\TopCustomers {}
}
