<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Product\Queries\ProductQuery} */
class_alias(\CraftCms\Commerce\Product\Queries\ProductQuery::class, 'craft\commerce\elements\db\ProductQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductQuery extends \CraftCms\Commerce\Product\Queries\ProductQuery {}
}
