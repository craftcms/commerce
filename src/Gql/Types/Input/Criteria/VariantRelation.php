<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Input\Criteria;

use CraftCms\Cms\Gql\Arguments\RelationCriteria;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Commerce\Gql\Arguments\Elements\Variant as VariantArguments;
use GraphQL\Type\Definition\InputObjectType;

class VariantRelation extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'VariantRelationCriteriaInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => fn() => [
                ...VariantArguments::getArguments(),
                ...VariantArguments::getContentArguments(),
                ...RelationCriteria::getArguments(),
            ],
        ]));
    }
}
