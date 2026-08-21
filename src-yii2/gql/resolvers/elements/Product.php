<?php

namespace craft\commerce\gql\resolvers\elements;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Resolvers\Elements\Product} */
class_alias(\CraftCms\Commerce\Gql\Resolvers\Elements\Product::class, 'craft\commerce\gql\resolvers\elements\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Gql\Resolvers\Elements\Product {}
}
