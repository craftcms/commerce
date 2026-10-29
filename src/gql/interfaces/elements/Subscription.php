<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\interfaces\elements;

use Craft;
use craft\commerce\gql\types\generators\SubscriptionType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\types\DateTime;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return SubscriptionType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all subscriptions.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        SubscriptionType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'SubscriptionInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'userId' => [
                'name' => 'userId',
                'type' => Type::int(),
                'description' => 'The ID of the subscriber’s user account.',
            ],
            'planId' => [
                'name' => 'planId',
                'type' => Type::int(),
                'description' => 'The ID of the subscription plan.',
            ],
            'planName' => [
                'name' => 'planName',
                'type' => Type::string(),
                'description' => 'The name of the subscription plan.',
            ],
            'orderId' => [
                'name' => 'orderId',
                'type' => Type::int(),
                'description' => 'The ID of the order that created the subscription.',
            ],
            'gatewayId' => [
                'name' => 'gatewayId',
                'type' => Type::int(),
                'description' => 'The ID of the subscription’s gateway.',
            ],
            'reference' => [
                'name' => 'reference',
                'type' => Type::string(),
                'description' => 'The subscription’s reference on the gateway.',
            ],
            'trialDays' => [
                'name' => 'trialDays',
                'type' => Type::int(),
                'description' => 'The number of trial days granted.',
            ],
            'isOnTrial' => [
                'name' => 'isOnTrial',
                'type' => Type::boolean(),
                'description' => 'Whether the subscription is on trial.',
            ],
            'nextPaymentDate' => [
                'name' => 'nextPaymentDate',
                'type' => DateTime::getType(),
                'description' => 'The date of the next payment.',
            ],
            'hasStarted' => [
                'name' => 'hasStarted',
                'type' => Type::boolean(),
                'description' => 'Whether the subscription has started.',
            ],
            'isCanceled' => [
                'name' => 'isCanceled',
                'type' => Type::boolean(),
                'description' => 'Whether the subscription is canceled.',
            ],
            'dateCanceled' => [
                'name' => 'dateCanceled',
                'type' => DateTime::getType(),
                'description' => 'The date the subscription was canceled.',
            ],
            'isExpired' => [
                'name' => 'isExpired',
                'type' => Type::boolean(),
                'description' => 'Whether the subscription has expired.',
            ],
            'dateExpired' => [
                'name' => 'dateExpired',
                'type' => DateTime::getType(),
                'description' => 'The date the subscription expired.',
            ],
            'isSuspended' => [
                'name' => 'isSuspended',
                'type' => Type::boolean(),
                'description' => 'Whether the subscription is on hold due to payment issues.',
            ],
            'dateSuspended' => [
                'name' => 'dateSuspended',
                'type' => DateTime::getType(),
                'description' => 'The date the subscription was put on hold.',
            ],
        ]), self::getName());
    }
}
