<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Subscription as SubscriptionElement;
use craft\commerce\gql\interfaces\elements\Subscription as SubscriptionInterface;
use craft\commerce\gql\types\elements\Subscription;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class SubscriptionType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class SubscriptionType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // Subscriptions have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(SubscriptionElement::GQL_TYPE_NAME, fn() => new Subscription([
            'name' => SubscriptionElement::GQL_TYPE_NAME,
            'fields' => function() use ($context) {
                $context ??= Craft::$app->getFields()->getLayoutByType(SubscriptionElement::class);
                $fields = array_merge(SubscriptionInterface::getFieldDefinitions(), self::getContentFields($context));
                return Craft::$app->getGql()->prepareFieldDefinitions($fields, SubscriptionElement::GQL_TYPE_NAME);
            },
        ]));
    }
}
