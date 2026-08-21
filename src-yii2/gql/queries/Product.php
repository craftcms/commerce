<?php

namespace craft\commerce\gql\queries;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Queries\Product} */
class_alias(\CraftCms\Commerce\Gql\Queries\Product::class, 'craft\commerce\gql\queries\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Gql\Queries\Product {}
}
