<?php

namespace craft\commerce\gql\types\elements;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Types\Elements\Product} */
class_alias(\CraftCms\Commerce\Gql\Types\Elements\Product::class, 'craft\commerce\gql\types\elements\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Gql\Types\Elements\Product {}
}
