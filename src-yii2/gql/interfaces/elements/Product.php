<?php

namespace craft\commerce\gql\interfaces\elements;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Interfaces\Elements\Product} */
class_alias(\CraftCms\Commerce\Gql\Interfaces\Elements\Product::class, 'craft\commerce\gql\interfaces\elements\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Gql\Interfaces\Elements\Product {}
}
