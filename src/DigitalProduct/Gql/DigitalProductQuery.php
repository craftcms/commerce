<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Gql;

use CraftCms\Cms\Gql\Queries\Query;
use CraftCms\Commerce\DigitalProduct\DigitalProducts;
use GraphQL\Type\Definition\Type;

/**
 * The `digitalProducts`, `digitalProductCount` and `digitalProduct` queries.
 */
class DigitalProductQuery extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        // The Digital Products plugin owns these queries until its data has been migrated.
        if (!app(DigitalProducts::class)->isEnabled()) {
            return [];
        }

        if ($checkToken && !DigitalProductGqlHelper::canQueryDigitalProducts()) {
            return [];
        }

        return [
            'digitalProducts' => [
                'type' => Type::listOf(DigitalProductInterface::getType()),
                'args' => DigitalProductArguments::getArguments(),
                'resolve' => DigitalProductResolver::class . '::resolve',
                'description' => 'Used to query for digital products.',
            ],
            'digitalProductCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => DigitalProductArguments::getArguments(),
                'resolve' => DigitalProductResolver::class . '::resolveCount',
                'description' => 'Used to return the number of digital products.',
            ],
            'digitalProduct' => [
                'type' => DigitalProductInterface::getType(),
                'args' => DigitalProductArguments::getArguments(),
                'resolve' => DigitalProductResolver::class . '::resolveOne',
                'description' => 'Used to query for a digital product.',
            ],
        ];
    }
}
