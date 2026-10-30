<?php

namespace craft\commerce\gql\types\generators;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Types\Generators\ProductType} */
class_alias(\CraftCms\Commerce\Gql\Types\Generators\ProductType::class, 'craft\commerce\gql\types\generators\ProductType');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductType extends \CraftCms\Commerce\Gql\Types\Generators\ProductType {}
}
