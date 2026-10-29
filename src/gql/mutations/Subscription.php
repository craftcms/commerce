<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\mutations;

use Craft;
use craft\commerce\elements\Subscription as SubscriptionElement;
use craft\commerce\gql\resolvers\mutations\Subscription as SubscriptionMutationResolver;
use craft\commerce\gql\types\generators\SubscriptionType;
use craft\gql\base\ElementMutationResolver;
use craft\gql\base\Mutation;
use craft\gql\base\MutationArguments;
use craft\helpers\Gql;
use GraphQL\Type\Definition\Type;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends Mutation
{
    /**
     * @inheritdoc
     */
    public static function getMutations(): array
    {
        $mutationList = [];

        if (Gql::canSchema('subscriptions.all', 'save')) {
            /** @var SubscriptionMutationResolver $resolver */
            $resolver = Craft::createObject(SubscriptionMutationResolver::class);
            static::prepareResolver($resolver, Craft::$app->getFields()->getLayoutByType(SubscriptionElement::class)->getCustomFields());

            $mutationList['save_Subscription'] = [
                'name' => 'save_Subscription',
                'description' => 'Save an existing subscription’s custom fields. Subscriptions are created and changed on their gateway.',
                'args' => array_merge(MutationArguments::getArguments(), $resolver->getResolutionData(ElementMutationResolver::CONTENT_FIELD_KEY)),
                'resolve' => [$resolver, 'saveSubscription'],
                'type' => SubscriptionType::generateType(null),
            ];
        }

        if (Gql::canSchema('subscriptions.all', 'delete')) {
            $mutationList['deleteSubscription'] = [
                'name' => 'deleteSubscription',
                'args' => [
                    'id' => Type::nonNull(Type::int()),
                    'hardDelete' => Type::boolean(),
                ],
                'resolve' => [Craft::createObject(SubscriptionMutationResolver::class), 'deleteSubscription'],
                'description' => 'Delete a subscription. This doesn’t cancel it on its gateway.',
                'type' => Type::boolean(),
            ];
        }

        return $mutationList;
    }
}
