<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\generators;

use Craft;
use craft\commerce\elements\Donation as DonationElement;
use craft\commerce\gql\interfaces\elements\Donation as DonationInterface;
use craft\commerce\gql\types\elements\Donation;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;

/**
 * Class DonationType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class DonationType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        // Donations have no context
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(DonationElement::GQL_TYPE_NAME, fn() => new Donation([
            'name' => DonationElement::GQL_TYPE_NAME,
            'fields' => function() use ($context) {
                $context ??= Craft::$app->getFields()->getLayoutByType(DonationElement::class);
                $fields = array_merge(DonationInterface::getFieldDefinitions(), self::getContentFields($context));
                return Craft::$app->getGql()->prepareFieldDefinitions($fields, DonationElement::GQL_TYPE_NAME);
            },
        ]));
    }
}
