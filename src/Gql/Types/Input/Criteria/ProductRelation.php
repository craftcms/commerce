<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Input\Criteria;

use CraftCms\Cms\Gql\Arguments\RelationCriteria;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Commerce\Gql\Arguments\Elements\Product as ProductArguments;
use GraphQL\Type\Definition\InputObjectType;

class ProductRelation extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'ProductRelationCriteriaInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => fn() => [
                ...ProductArguments::getArguments(),
                ...ProductArguments::getContentArguments(),
                ...RelationCriteria::getArguments(),
            ],
        ]));
    }
}
