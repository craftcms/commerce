<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Queries;

use CraftCms\Cms\Gql\Queries\Query;
use CraftCms\Commerce\Gql\Arguments\Elements\Product as ProductArguments;
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Gql\Resolvers\Elements\Product as ProductResolver;
use CraftCms\Commerce\Helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

class Product extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'products' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolve',
                'description' => 'This query is used to query for products.',
            ],
            'productCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of products.',
            ],
            'product' => [
                'type' => ProductInterface::getType(),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a product.',
            ],
        ];
    }
}
