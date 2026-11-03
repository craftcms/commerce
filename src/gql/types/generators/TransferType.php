<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Transfer as TransferElement;
use craft\commerce\gql\interfaces\elements\Transfer as TransferInterface;
use craft\commerce\gql\types\elements\Transfer;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class TransferType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class TransferType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // Transfers have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(TransferElement::GQL_TYPE_NAME, fn() => new Transfer([
            'name' => TransferElement::GQL_TYPE_NAME,
            'fields' => function() use ($context) {
                $context ??= Craft::$app->getFields()->getLayoutByType(TransferElement::class);
                $fields = array_merge(TransferInterface::getFieldDefinitions(), self::getContentFields($context));
                return Craft::$app->getGql()->prepareFieldDefinitions($fields, TransferElement::GQL_TYPE_NAME);
            },
        ]));
    }
}
