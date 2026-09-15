<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Input;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Commerce\Gql\Arguments\Elements\Variant as VariantArguments;
use GraphQL\Type\Definition\InputObjectType;

class Variant extends InputObjectType
{
    public static function getType(): mixed
    {
        $typeName = 'VariantInput';

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new InputObjectType([
            'name' => $typeName,
            'fields' => VariantArguments::getArguments(...),
        ]));
    }
}
