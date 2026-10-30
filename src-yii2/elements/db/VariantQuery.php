<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Product\Variant\Queries\VariantQuery} */
class_alias(\CraftCms\Commerce\Product\Variant\Queries\VariantQuery::class, 'craft\commerce\elements\db\VariantQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class VariantQuery extends \CraftCms\Commerce\Product\Variant\Queries\VariantQuery {}
}
