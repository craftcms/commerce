<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Product as ProductArguments;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\gql\resolvers\elements\Product as ProductResolver;
use craft\commerce\helpers\Gql as GqlHelper;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries($checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'products' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolve',
                'description' => 'This query is used to query for products.'
            ],
            'productCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of products.'
            ],
            'product' => [
                'type' => ProductInterface::getType(),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a product.'
            ],
        ];
    }
}