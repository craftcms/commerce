<?php

namespace craft\commerce\widgets;

/** @deprecated use {@see \CraftCms\Commerce\Dashboard\Widgets\NewCustomers} */
class_alias(\CraftCms\Commerce\Dashboard\Widgets\NewCustomers::class, 'craft\commerce\widgets\NewCustomers');

/** @phpstan-ignore-next-line */
if (false) {
    class NewCustomers extends \CraftCms\Commerce\Dashboard\Widgets\NewCustomers {}
}
