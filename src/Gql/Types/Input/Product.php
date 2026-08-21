<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Input;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Commerce\Gql\Arguments\Elements\Product as ProductArguments;
use GraphQL\Type\Definition\InputObjectType;

class Product extends InputObjectType
{
    public static function getType(): mixed
    {
        $typeName = 'ProductInput';

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new InputObjectType([
            'name' => $typeName,
            'fields' => ProductArguments::getArguments(...),
        ]));
    }
}
