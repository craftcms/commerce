<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;

class DigitalProductGqlHelper extends GqlHelper
{
    public static function canQueryDigitalProducts(): bool
    {
        return isset(self::extractAllowedEntitiesFromSchema()['digitalProductTypes']);
    }

    /**
     * @return DigitalProductType[]
     */
    public static function getSchemaContainedTypes(): array
    {
        return array_values(array_filter(
            app(DigitalProductTypes::class)->getAllDigitalProductTypes(),
            fn(DigitalProductType $type) => static::isSchemaAwareOf("digitalProductTypes.$type->uid"),
        ));
    }
}
