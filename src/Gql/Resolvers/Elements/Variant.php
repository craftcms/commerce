<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Resolvers\Elements;

use BadMethodCallException;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Resolvers\ElementResolver;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Gql as GqlCommerceHelper;
use CraftCms\Commerce\Product\Elements\Product as ProductElement;
use CraftCms\Commerce\Product\Variant\Elements\Variant as VariantElement;
use CraftCms\Commerce\Product\Variant\Queries\VariantQuery;

class Variant extends ElementResolver
{
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = VariantElement::find();
        // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

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

        if (!GqlCommerceHelper::canQueryProducts()) {
            return ElementCollection::empty();
        }

        // For variant queries make sure we are only return those that have live products
        // unless the schema allows querying inactive elements
        if (!GqlHelper::canQueryInactiveElements() && $query instanceof VariantQuery) {
            $query->productStatus(ProductElement::STATUS_LIVE);
        }

        $productTypeIds = array_map(fn($productType) => $productType->id, GqlCommerceHelper::getSchemaContainedProductTypes());
        $query->whereIn(Table::PRODUCTS . '.typeId', $productTypeIds);

        return $query;
    }
}
