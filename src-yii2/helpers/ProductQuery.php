<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\ProductQuery} */
class_alias(\CraftCms\Commerce\Helpers\ProductQuery::class, 'craft\commerce\helpers\ProductQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductQuery extends \CraftCms\Commerce\Helpers\ProductQuery {}
}
