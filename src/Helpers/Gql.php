<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\GqlHelper;

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
            Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
            fn(ProductType $productType) => static::isSchemaAwareOf("productTypes.$productType->uid", $schema),
        );
    }
}
