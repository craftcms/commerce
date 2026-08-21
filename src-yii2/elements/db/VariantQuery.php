<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Queries\VariantQuery} */
class_alias(\CraftCms\Commerce\Catalog\Queries\VariantQuery::class, 'craft\commerce\elements\db\VariantQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class VariantQuery extends \CraftCms\Commerce\Catalog\Queries\VariantQuery {}
}
