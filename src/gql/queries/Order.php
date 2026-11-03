<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Order as OrderArguments;
use craft\commerce\gql\interfaces\elements\Order as OrderInterface;
use craft\commerce\gql\resolvers\elements\Order as OrderResolver;
use craft\commerce\helpers\Gql as GqlHelper;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryOrders()) {
            return [];
        }

        return [
            'orders' => [
                'type' => Type::listOf(OrderInterface::getType()),
                'args' => OrderArguments::getArguments(),
                'resolve' => OrderResolver::class . '::resolve',
                'description' => 'This query is used to query for orders.',
            ],
            'orderCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => OrderArguments::getArguments(),
                'resolve' => OrderResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of orders.',
            ],
            'order' => [
                'type' => OrderInterface::getType(),
                'args' => OrderArguments::getArguments(),
                'resolve' => OrderResolver::class . '::resolveOne',
                'description' => 'This query is used to query for an order.',
            ],
        ];
    }
}
