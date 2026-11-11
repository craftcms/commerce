<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use BadMethodCallException;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Resolvers\ElementResolver;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;

class DigitalProductResolver extends ElementResolver
{
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        $query = $source === null ? DigitalProduct::find() : $source->$fieldName;

        // If it's preloaded, it's preloaded.
        if (!$query instanceof ElementQueryInterface) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            try {
                $query->$key($value);
            } catch (BadMethodCallException $e) {
                if ($value !== null) {
                    throw $e;
                }
            }
        }

        GqlHelper::extractAllowedEntitiesFromSchema();

        if (!DigitalProductGqlHelper::canQueryDigitalProducts()) {
            return ElementCollection::empty();
        }

        $query->whereIn('commerce_digitalproducts.typeId', array_map(
            fn(DigitalProductType $type) => $type->id,
            DigitalProductGqlHelper::getSchemaContainedTypes(),
        ));

        return $query;
    }
}
