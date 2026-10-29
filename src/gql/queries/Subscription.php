<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\queries;

use craft\commerce\gql\arguments\elements\Subscription as SubscriptionArguments;
use craft\commerce\gql\interfaces\elements\Subscription as SubscriptionInterface;
use craft\commerce\gql\resolvers\elements\Subscription as SubscriptionResolver;
use craft\gql\base\Query;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canSchema('subscriptions.all')) {
            return [];
        }

        return [
            'subscriptions' => [
                'type' => Type::listOf(SubscriptionInterface::getType()),
                'args' => SubscriptionArguments::getArguments(),
                'resolve' => SubscriptionResolver::class . '::resolve',
                'description' => 'This query is used to query for subscriptions.',
            ],
            'subscriptionCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => SubscriptionArguments::getArguments(),
                'resolve' => SubscriptionResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of subscriptions.',
            ],
            'subscription' => [
                'type' => SubscriptionInterface::getType(),
                'args' => SubscriptionArguments::getArguments(),
                'resolve' => SubscriptionResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a subscription.',
            ],
        ];
    }
}
