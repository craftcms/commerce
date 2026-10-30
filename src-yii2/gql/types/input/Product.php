<?php

namespace craft\commerce\gql\types\input;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Types\Input\Product} */
class_alias(\CraftCms\Commerce\Gql\Types\Input\Product::class, 'craft\commerce\gql\types\input\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Gql\Types\Input\Product {}
}
