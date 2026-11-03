<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types;

use Craft;
use craft\gql\base\ObjectType;
use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

/**
 * Class TransferDetail
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class TransferDetail extends ObjectType implements SingularTypeInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'TransferDetail';
    }

    /**
     * @inheritdoc
     */
    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new self([
            'name' => self::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the type for the items in inventory transfers.',
        ]));
    }

    /**
     * Returns the type’s field definitions.
     *
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions([
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The ID of the transfer item.',
            ],
            'inventoryItemId' => [
                'name' => 'inventoryItemId',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The ID of the inventory item being transferred.',
            ],
            'inventoryItemDescription' => [
                'name' => 'inventoryItemDescription',
                'type' => Type::string(),
                'description' => 'The description of the inventory item being transferred.',
            ],
            'quantity' => [
                'name' => 'quantity',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The quantity being transferred.',
            ],
            'quantityAccepted' => [
                'name' => 'quantityAccepted',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The quantity received and accepted.',
            ],
            'quantityRejected' => [
                'name' => 'quantityRejected',
                'type' => Type::nonNull(Type::int()),
                'description' => 'The quantity received and rejected.',
            ],
        ], self::getName());
    }
}
