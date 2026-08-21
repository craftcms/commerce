<?php

namespace craft\commerce\gql\types;

/** @deprecated use {@see \CraftCms\Commerce\Gql\Types\SaleType} */
class_alias(\CraftCms\Commerce\Gql\Types\SaleType::class, 'craft\commerce\gql\types\SaleType');

/** @phpstan-ignore-next-line */
if (false) {
    class SaleType extends \CraftCms\Commerce\Gql\Types\SaleType {}
}
