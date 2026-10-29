<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\arguments\elements;

use Craft;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\commerce\elements\Subscription as SubscriptionElement;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'userId' => [
                'name' => 'userId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the subscribers’ user IDs.',
            ],
            'planId' => [
                'name' => 'planId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the subscription plans’ IDs.',
            ],
            'plan' => [
                'name' => 'plan',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the subscription plans’ handles.',
            ],
            'orderId' => [
                'name' => 'orderId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the IDs of the orders that created the subscriptions.',
            ],
            'reference' => [
                'name' => 'reference',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the subscriptions’ gateway references.',
            ],
            'onTrial' => [
                'name' => 'onTrial',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only subscriptions that are on trial (or not).',
            ],
            'hasStarted' => [
                'name' => 'hasStarted',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only subscriptions that have started (or not).',
            ],
            'isCanceled' => [
                'name' => 'isCanceled',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only subscriptions that are canceled (or not).',
            ],
            'isExpired' => [
                'name' => 'isExpired',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only subscriptions that have expired (or not).',
            ],
            'isSuspended' => [
                'name' => 'isSuspended',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results to only subscriptions that are suspended (or not).',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        $contentArguments = [];

        foreach (Craft::$app->getFields()->getLayoutByType(SubscriptionElement::class)->getCustomFields() as $contentField) {
            if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                $contentArguments[$contentField->handle] = $contentField->getContentGqlQueryArgumentType();
            }
        }

        return array_merge(parent::getContentArguments(), $contentArguments);
    }
}
