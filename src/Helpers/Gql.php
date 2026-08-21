<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;

class Gql extends GqlHelper
{
    public static function canQueryProducts(): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        return isset($allowedEntities['productTypes']);
    }

    /**
     * @return ProductType[]
     */
    public static function getSchemaContainedProductTypes(?GqlSchema $schema = null): array
    {
        return array_filter(
            app(ProductTypes::class)->getAllProductTypes(),
            fn(ProductType $productType) => static::isSchemaAwareOf("productTypes.$productType->uid", $schema),
        );
    }
}
