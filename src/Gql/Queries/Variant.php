<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Queries;

use CraftCms\Cms\Gql\Queries\Query;
use CraftCms\Commerce\Gql\Arguments\Elements\Variant as VariantArguments;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use CraftCms\Commerce\Gql\Resolvers\Elements\Variant as VariantResolver;
use CraftCms\Commerce\Helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

class Variant extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'variants' => [
                'type' => Type::listOf(VariantInterface::getType()),
                'args' => VariantArguments::getArguments(),
                'resolve' => VariantResolver::class . '::resolve',
                'description' => 'This query is used to query for variants.',
            ],
            'variantCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => VariantArguments::getArguments(),
                'resolve' => VariantResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of variants.',
            ],
            'variant' => [
                'type' => VariantInterface::getType(),
                'args' => VariantArguments::getArguments(),
                'resolve' => VariantResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a variant.',
            ],
        ];
    }
}
