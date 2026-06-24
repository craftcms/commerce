<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\helpers\Gql as GqlHelper;
use CraftCms\Cms\Gql\Models\GqlSchema;

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
