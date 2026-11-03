<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\gql\interfaces\elements\Order as OrderInterface;
use craft\commerce\gql\types\elements\Order;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class OrderType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class OrderType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // Orders have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(OrderElement::GQL_TYPE_NAME, fn() => new Order([
            'name' => OrderElement::GQL_TYPE_NAME,
            'fields' => function() use ($context) {
                // Orders don't have different types, so the context for an order will be the same every time.
                $context ??= Craft::$app->getFields()->getLayoutByType(OrderElement::class);
                $contentFieldGqlTypes = self::getContentFields($context);
                $orderFields = array_merge(OrderInterface::getFieldDefinitions(), $contentFieldGqlTypes);
                return Craft::$app->getGql()->prepareFieldDefinitions($orderFields, OrderElement::GQL_TYPE_NAME);
            },
        ]));
    }
}
