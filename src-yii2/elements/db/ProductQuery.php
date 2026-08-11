<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Queries\ProductQuery} */
class_alias(\CraftCms\Commerce\Catalog\Queries\ProductQuery::class, 'craft\commerce\elements\db\ProductQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductQuery extends \CraftCms\Commerce\Catalog\Queries\ProductQuery {}
}
